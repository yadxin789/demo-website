<?php

namespace App\Modules\Shop\Services\Inventory;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\StockCount;
use App\Modules\Shop\Models\StockCountLine;
use App\Modules\Shop\Models\StockLevel;
use App\Modules\Shop\Models\StockMovement;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Scan\ScanResolver;
use App\Modules\Shop\Support\ScanCode;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 盘点服务：按仓库/库位带出账面数，录入实盘数后自动计算盈亏数量与盈亏金额（标准成本）。
 * 草稿/盘点中状态可修改；过账调整在 P1-207 由过账流程处理。
 */
class CountService
{
    public function __construct(
        protected SequenceGenerator $sequences,
        protected StockService $stock,
        protected InventoryAuditor $auditor,
        protected AuditLogger $logger,
        protected ScanResolver $scanner,
        protected EntryService $entries,
    ) {
    }

    /**
     * 创建盘点单并按库存维度带出账面行。
     *
     * @param  array<string, mixed>  $data
     */
    public function createCount(array $data): StockCount
    {
        return DB::transaction(function () use ($data) {
            $warehouseId = (int) $data['warehouse_id'];
            $locationId = isset($data['location_id']) ? (int) $data['location_id'] : null;
            $productIds = ! empty($data['product_ids']) ? array_values(array_map('intval', (array) $data['product_ids'])) : null;

            $count = StockCount::create([
                'code' => $this->sequences->generate('count'),
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'count_date' => isset($data['count_date']) ? Carbon::parse($data['count_date']) : Carbon::today(),
                'status' => StockCount::STATUS_DRAFT,
                'operator_id' => $data['operator_id'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            $levels = StockLevel::query()
                ->where('warehouse_id', $warehouseId)
                ->when($locationId !== null, fn ($query) => $query->where('location_id', $locationId))
                ->when($productIds !== null, fn ($query) => $query->whereIn('product_id', $productIds))
                ->get();

            foreach ($levels as $level) {
                $product = Product::find($level->product_id);
                $count->lines()->create([
                    'product_id' => $level->product_id,
                    'location_id' => $level->location_id,
                    'lot_no' => $level->lot_no,
                    'serial_no' => $level->serial_no,
                    'book_qty' => (float) $level->qty,
                    'counted_qty' => null,
                    'variance_qty' => 0,
                    'unit_cost' => (float) ($product?->standard_cost ?? $level->avg_cost),
                    'variance_value' => 0,
                ]);
            }

            return $count->load('lines.product');
        });
    }

    /**
     * 录入/更新实盘数，重算盈亏。
     *
     * @param  array<int, array<string, mixed>>  $lines  [{line_id, counted_qty}, ...]
     */
    public function enterCounted(StockCount $count, array $lines): StockCount
    {
        if (! $count->isEditable()) {
            throw new InventoryException("盘点单 {$count->code} 当前状态不可录入实盘数");
        }

        return DB::transaction(function () use ($count, $lines) {
            $byLine = collect($lines)->keyBy(fn ($line) => (int) $line['line_id']);

            foreach ($count->lines as $line) {
                if (! $byLine->has($line->id)) {
                    continue;
                }

                $raw = $byLine->get($line->id)['counted_qty'] ?? null;
                if ($raw === null || $raw === '') {
                    throw new InventoryException("商品 {$line->product?->code} 实盘数量不能为空");
                }

                $counted = (float) $raw;
                if ($counted < 0) {
                    throw new InventoryException("商品 {$line->product?->code} 实盘数量不能为负");
                }

                $variance = $counted - (float) $line->book_qty;
                $line->counted_qty = $counted;
                $line->variance_qty = $variance;
                $line->variance_value = $variance * (float) $line->unit_cost;
                $line->save();
            }

            if ($count->status === StockCount::STATUS_DRAFT) {
                $count->status = StockCount::STATUS_COUNTING;
                $count->save();
            }

            return $count->load('lines.product');
        });
    }

    /**
     * 扫码实盘：定位商品/批次/序列号行并累加；无账面行时创建账面数为 0 的盘盈行。
     *
     * @return array<string, mixed>
     */
    public function scanCounted(StockCount $count, string $code, float $qty = 1): array
    {
        if (! $count->isEditable()) {
            throw new InventoryException("盘点单 {$count->code} 当前状态不可扫码录入");
        }

        if ($qty <= 0) {
            throw new InventoryException('扫码数量必须大于 0');
        }

        $result = $this->scanner->resolve($code, (int) $count->warehouse_id);

        return DB::transaction(function () use ($count, $result, $qty) {
            $type = $result['type'];
            $data = $result['data'];

            if (! in_array($type, [ScanCode::PRODUCT, ScanCode::LOT, ScanCode::SERIAL], true)) {
                throw new InventoryException('请扫描商品、批次或序列号参与盘点');
            }

            $product = Product::findOrFail($data['product_id'] ?? $data['id']);

            $lotNo = $type === ScanCode::LOT ? $data['lot_no'] : null;
            $serialNo = $type === ScanCode::SERIAL ? $data['serial_no'] : null;

            if ($type === ScanCode::PRODUCT && $product->traceability === 'serial') {
                throw new InventoryException("商品 {$product->code} 为序列号追踪，请逐个扫描序列号");
            }

            if ($type === ScanCode::PRODUCT && $product->traceability === 'lot') {
                throw new InventoryException("商品 {$product->code} 为批次追踪，请扫描批次码");
            }

            if ($type === ScanCode::LOT && $product->traceability !== 'lot') {
                throw new InventoryException("商品 {$product->code} 不是批次追踪商品");
            }

            if ($type === ScanCode::SERIAL && $product->traceability !== 'serial') {
                throw new InventoryException("商品 {$product->code} 不是序列号追踪商品");
            }

            $line = StockCountLine::query()
                ->where('count_id', $count->id)
                ->where('product_id', $product->id)
                ->when($lotNo !== null, fn ($query) => $query->where('lot_no', $lotNo),
                    fn ($query) => $query->whereNull('lot_no'))
                ->when($serialNo !== null, fn ($query) => $query->where('serial_no', $serialNo),
                    fn ($query) => $query->whereNull('serial_no'))
                ->when($count->location_id !== null, fn ($query) => $query->where('location_id', $count->location_id))
                ->lockForUpdate()
                ->first();

            $created = false;
            if (! $line) {
                $line = new StockCountLine([
                    'count_id' => $count->id,
                    'product_id' => $product->id,
                    'location_id' => $count->location_id ?? ($data['location_id'] ?? null),
                    'lot_no' => $lotNo,
                    'serial_no' => $serialNo,
                    'book_qty' => 0,
                    'counted_qty' => 0,
                    'variance_qty' => 0,
                    'unit_cost' => (float) $product->standard_cost,
                    'variance_value' => 0,
                ]);
                $created = true;
            }

            if ($type === ScanCode::SERIAL) {
                $line->counted_qty = 1;
            } else {
                $line->counted_qty = (float) ($line->counted_qty ?? 0) + $qty;
            }

            $line->variance_qty = (float) $line->counted_qty - (float) $line->book_qty;
            $line->variance_value = (float) $line->variance_qty * (float) $line->unit_cost;
            $line->save();

            if ($count->status === StockCount::STATUS_DRAFT) {
                $count->status = StockCount::STATUS_COUNTING;
                $count->save();
            }

            return [
                'line_id' => $line->id,
                'created' => $created,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'location_id' => $line->location_id,
                'location_code' => $line->location?->code,
                'lot_no' => $line->lot_no,
                'serial_no' => $line->serial_no,
                'book_qty' => (float) $line->book_qty,
                'counted_qty' => (float) $line->counted_qty,
                'unit_cost' => (float) $line->unit_cost,
                'variance_qty' => (float) $line->variance_qty,
                'variance_value' => (float) $line->variance_value,
            ];
        });
    }

    /** 未录入实盘的行数。 */
    public function pendingCount(StockCount $count): int
    {
        return $count->lines->filter(fn (StockCountLine $line) => $line->counted_qty === null)->count();
    }

    /** 盈亏金额合计（盘盈为正、盘亏为负）。 */
    public function totalVarianceValue(StockCount $count): float
    {
        return (float) $count->lines->sum('variance_value');
    }

    /**
     * 盘点过账：盘盈入库、盘亏出库，写库存流水与审计；重复过账拦截。
     * 待处理财产损溢会计分录在 P1-603 分录服务接入后生成，本期仅预留占位。
     */
    public function postCount(StockCount $count): StockCount
    {
        if ($count->status === StockCount::STATUS_ADJUSTED) {
            throw new InventoryException("盘点单 {$count->code} 已过账，不能重复过账");
        }

        if ($count->status === StockCount::STATUS_CANCELLED) {
            throw new InventoryException("盘点单 {$count->code} 已取消，不能过账");
        }

        if ($this->pendingCount($count) > 0) {
            throw new InventoryException("盘点单 {$count->code} 尚有未录入实盘的行，不能过账");
        }

        return DB::transaction(function () use ($count) {
            foreach ($count->lines as $line) {
                $variance = (float) $line->variance_qty;

                if (abs($variance) < 0.0000001) {
                    $line->adjusted_at = Carbon::now();
                    $line->save();
                    continue;
                }

                $level = $this->stock->findLevel(
                    $line->product_id,
                    $count->warehouse_id,
                    $line->location_id,
                    $line->lot_no,
                    $line->serial_no
                );

                $before = $level ? (float) $level->qty : 0;
                $after = (float) $line->counted_qty;

                if (! $level) {
                    $level = new StockLevel([
                        'product_id' => $line->product_id,
                        'warehouse_id' => $count->warehouse_id,
                        'location_id' => $line->location_id,
                        'lot_no' => $line->lot_no,
                        'serial_no' => $line->serial_no,
                    ]);
                }

                $level->qty = $after;
                $level->avg_cost = (float) $line->unit_cost;
                $level->save();

                $movement = StockMovement::create([
                    'product_id' => $line->product_id,
                    'warehouse_id' => $count->warehouse_id,
                    'location_id' => $line->location_id,
                    'lot_no' => $line->lot_no,
                    'serial_no' => $line->serial_no,
                    'direction' => $variance > 0 ? StockMovement::DIRECTION_IN : StockMovement::DIRECTION_OUT,
                    'qty_before' => $before,
                    'qty_after' => $after,
                    'qty' => $variance > 0 ? $variance : -abs($variance),
                    'unit_cost' => (float) $line->unit_cost,
                    'movement_date' => $count->count_date,
                    'source_type' => 'adjust',
                    'source_id' => $count->id,
                    'source_code' => $count->code,
                    'operator_id' => $count->operator_id,
                    'note' => '盘点过账',
                    'created_at' => Carbon::now(),
                ]);

                $this->auditor->logMovement($movement);

                $line->adjusted_at = Carbon::now();
                $line->save();
            }

            $count->status = StockCount::STATUS_ADJUSTED;
            $count->save();

            $entry = $this->postVarianceEntry($count);
            if ($entry !== null) {
                $count->entry_id = $entry->id;
                $count->save();
            }

            $this->logger->record([
                'module' => 'warehouse',
                'action' => AuditLog::ACTION_APPROVE,
                'model' => StockCount::class,
                'record_id' => $count->id,
                'document_no' => $count->code,
                'message' => "盘点单 {$count->code} 过账，盈亏金额 " . number_format($this->totalVarianceValue($count), 2),
                'context' => [
                    'count_id' => $count->id,
                    'variance_value' => $this->totalVarianceValue($count),
                    'line_count' => $count->lines->count(),
                ],
            ]);

            return $count->load('lines.product');
        });
    }

    /**
     * 盘点差异凭证：盘盈借库存商品/贷待处理财产损溢，盘亏借待处理财产损溢/贷库存商品。
     * 后续损溢审批转费用或营业外支出仍由人工凭证处理。
     */
    private function postVarianceEntry(StockCount $count): ?\App\Modules\Shop\Models\AccountEntry
    {
        $gain = 0.0;
        $loss = 0.0;

        foreach ($count->lines as $line) {
            $value = (float) $line->variance_value;
            if ($value > 0) {
                $gain += $value;
            } elseif ($value < 0) {
                $loss += abs($value);
            }
        }

        $gain = round($gain, 4);
        $loss = round($loss, 4);
        if ($gain <= 0 && $loss <= 0) {
            return null;
        }

        $journal = Journal::where('code', 'MISC')->firstOrFail();
        $lines = [];

        if ($gain > 0) {
            $lines[] = ['account_code' => '1001', 'direction' => 'debit', 'amount' => $gain, 'memo' => '盘点盘盈'];
            $lines[] = ['account_code' => '1101', 'direction' => 'credit', 'amount' => $gain, 'memo' => '盘点盘盈待处理'];
        }
        if ($loss > 0) {
            $lines[] = ['account_code' => '1101', 'direction' => 'debit', 'amount' => $loss, 'memo' => '盘点盘亏待处理'];
            $lines[] = ['account_code' => '1001', 'direction' => 'credit', 'amount' => $loss, 'memo' => '盘点盘亏'];
        }

        $entry = $this->entries->createEntry([
            'journal_id' => $journal->id,
            'entry_date' => $count->count_date?->toDateString(),
            'source_type' => 'stock_count',
            'source_id' => $count->id,
            'source_code' => $count->code,
            'description' => "盘点差异 {$count->code}",
        ], $lines);

        return $this->entries->post($entry);
    }
}
