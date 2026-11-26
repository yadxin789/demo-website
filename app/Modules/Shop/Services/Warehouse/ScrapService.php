<?php

namespace App\Modules\Shop\Services\Warehouse;

use App\Modules\Shop\Exceptions\InventoryException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Journal;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\Scrap;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Inventory\OutboundService;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 报废服务：商品从库存出库并结转损失（管理费用 / 营业外支出）。
 * 草稿阶段不扣库存；过账时逐行出库、按标准成本生成损失分录并锁定单据。
 */
class ScrapService
{
    /** 损失类型 → 借方损失科目编码。 */
    private const LOSS_ACCOUNT_MAP = [
        Scrap::LOSS_EXPENSE => '5002',
        Scrap::LOSS_OTHER => '5003',
    ];

    public function __construct(
        protected SequenceGenerator $sequences,
        protected OutboundService $outbound,
        protected EntryService $entries,
        protected AuditLogger $logger
    ) {
    }

    /**
     * 创建报废草稿单。
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createScrap(array $data, array $lines): Scrap
    {
        if ($lines === []) {
            throw new InventoryException('报废单至少需要一行商品');
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if ($reason === '') {
            throw new InventoryException('报废原因必填');
        }

        $lossType = in_array($data['loss_type'] ?? '', [Scrap::LOSS_EXPENSE, Scrap::LOSS_OTHER], true)
            ? $data['loss_type']
            : Scrap::LOSS_EXPENSE;

        return DB::transaction(function () use ($data, $lines, $reason, $lossType) {
            $scrap = Scrap::create([
                'code' => $this->sequences->generate('scrap'),
                'warehouse_id' => (int) $data['warehouse_id'],
                'scrap_date' => isset($data['scrap_date']) ? Carbon::parse($data['scrap_date']) : Carbon::today(),
                'status' => Scrap::STATUS_DRAFT,
                'loss_type' => $lossType,
                'reason' => $reason,
                'operator_id' => $data['operator_id'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                $product = Product::findOrFail($line['product_id']);
                $qty = (float) ($line['qty'] ?? 0);
                if ($qty <= 0) {
                    throw new InventoryException("商品 {$product->code} 报废数量必须大于 0");
                }

                $stdCost = (float) $product->standard_cost;
                $lossAmount = round($qty * $stdCost, 4);

                $scrap->lines()->create([
                    'product_id' => $product->id,
                    'location_id' => $line['location_id'] ?? null,
                    'lot_no' => $line['lot_no'] ?? null,
                    'serial_no' => $line['serial_no'] ?? null,
                    'qty' => $qty,
                    'unit_cost' => $stdCost,
                    'loss_amount' => $lossAmount,
                    'reason' => $line['reason'] ?? null,
                ]);
            }

            $this->logger->record([
                'module' => 'warehouse',
                'action' => AuditLog::ACTION_CREATE,
                'model' => Scrap::class,
                'record_id' => $scrap->id,
                'document_no' => $scrap->code,
                'message' => "报废单 {$scrap->code} 草稿创建，原因：{$reason}",
                'context' => [
                    'warehouse_id' => $scrap->warehouse_id,
                    'loss_type' => $lossType,
                    'line_count' => $scrap->lines->count(),
                ],
            ]);

            return $scrap->load('lines.product');
        });
    }

    /**
     * 报废过账：出库扣减库存，按标准成本生成损失分录并过账；重复过账拦截。
     */
    public function postScrap(Scrap $scrap): Scrap
    {
        if ($scrap->status === Scrap::STATUS_DONE) {
            throw new InventoryException("报废单 {$scrap->code} 已过账，不能重复过账");
        }

        if ($scrap->status === Scrap::STATUS_CANCELLED) {
            throw new InventoryException("报废单 {$scrap->code} 已取消，不能过账");
        }

        return DB::transaction(function () use ($scrap) {
            $lossAccount = self::LOSS_ACCOUNT_MAP[$scrap->loss_type] ?? '5002';
            $entryLines = [];
            $totalLoss = 0.0;

            foreach ($scrap->lines as $line) {
                $product = Product::findOrFail($line->product_id);
                $qty = (float) $line->qty;
                $lossAmount = (float) $line->loss_amount;
                $totalLoss += $lossAmount;

                $this->outbound->outbound([
                    'product_id' => $product->id,
                    'warehouse_id' => $scrap->warehouse_id,
                    'location_id' => $line->location_id,
                    'qty' => $qty,
                    'lot_no' => $line->lot_no,
                    'serials' => $product->traceability === 'serial' && $line->serial_no ? [$line->serial_no] : [],
                    'unit_cost' => (float) $line->unit_cost,
                    'source_type' => 'scrap',
                    'source_id' => $scrap->id,
                    'source_code' => $scrap->code,
                    'operator_id' => $scrap->operator_id,
                    'note' => "报废 {$product->code}：{$scrap->reason}",
                    'movement_date' => $scrap->scrap_date?->toDateString(),
                ]);

                if ($lossAmount > 0) {
                    $memo = "{$product->code} 报废 {$qty}";
                    $entryLines[] = ['account_code' => $lossAccount, 'direction' => 'debit', 'amount' => $lossAmount, 'memo' => $memo];
                    $entryLines[] = ['account_code' => '1001', 'direction' => 'credit', 'amount' => $lossAmount, 'memo' => $memo];
                }
            }

            $this->postLossEntry($scrap, $entryLines);

            $scrap->status = Scrap::STATUS_DONE;
            $scrap->save();

            $this->logger->record([
                'module' => 'warehouse',
                'action' => AuditLog::ACTION_APPROVE,
                'model' => Scrap::class,
                'record_id' => $scrap->id,
                'document_no' => $scrap->code,
                'message' => "报废单 {$scrap->code} 过账，损失金额 " . number_format($totalLoss, 4),
                'context' => [
                    'loss_type' => $scrap->loss_type,
                    'loss_account' => $lossAccount,
                    'total_loss' => round($totalLoss, 4),
                    'line_count' => $scrap->lines->count(),
                    'reason' => $scrap->reason,
                ],
            ]);

            return $scrap->load('lines.product');
        });
    }

    /** 报废损失分录：借 损失科目 / 贷 库存商品，按标准成本金额生成并过账。 */
    private function postLossEntry(Scrap $scrap, array $entryLines): void
    {
        if ($entryLines === []) {
            return;
        }

        $journal = Journal::where('code', 'MISC')->firstOrFail();
        $entry = $this->entries->createEntry([
            'journal_id' => $journal->id,
            'entry_date' => $scrap->scrap_date?->toDateString(),
            'source_type' => 'scrap',
            'source_id' => $scrap->id,
            'source_code' => $scrap->code,
            'description' => "报废损失 {$scrap->code}",
        ], $entryLines);

        $this->entries->post($entry);
    }
}
