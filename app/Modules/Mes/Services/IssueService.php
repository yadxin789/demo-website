<?php

declare(strict_types=1);

namespace App\Modules\Mes\Services;

use App\Modules\Mes\Models\Material;
use App\Modules\Mes\Models\MaterialIssue;
use App\Modules\Mes\Models\MaterialIssueLine;
use App\Modules\Mes\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 领料与发料（M3-04 / M3-06）。
 *
 * 两条主线：
 *   1. createFromOrder：按工单的 BOM × 计划数量 × (1+损耗率) 展开需求，生成一张领料单；
 *      同一物料在 BOM 里出现多行时合并成一行，避免仓库按两趟发。
 *   2. issue：逐行录入实发数量，扣减库存，行/单状态随之推进。
 *
 * 状态口径：
 *   行 pending（一点没发）→ partial（发了一部分）→ issued（发满）
 *   单 draft（还没动）→ partial（有行没发完）→ issued（全部发满）
 */
class IssueService
{
    /** 按工单生成领料单；已有领料单时返回既有的那张，不重复建。 */
    public function createFromOrder(WorkOrder $order): MaterialIssue
    {
        $existing = MaterialIssue::query()->where('work_order_id', $order->id)->first();
        if ($existing) {
            return $existing;
        }

        if (! $order->bom) {
            throw new RuntimeException('这张工单没有挂 BOM，算不出用料，先去主数据给产品配一份 BOM。');
        }

        // 汇总需求：同一物料多行合并
        $needs = [];
        foreach ($order->bom->lines as $line) {
            $materialId = (int) $line->component_material_id;
            $need = (float) $line->qty_per * (1 + (float) $line->loss_rate) * (float) $order->plan_qty;
            if (! isset($needs[$materialId])) {
                $needs[$materialId] = 0.0;
            }
            $needs[$materialId] += $need;
        }

        return DB::connection('mes')->transaction(function () use ($order, $needs) {
            $issue = MaterialIssue::query()->create([
                'issue_no' => $this->nextIssueNo(),
                'work_order_id' => $order->id,
                'status' => 'draft',
                'remark' => null,
            ]);

            foreach ($needs as $materialId => $need) {
                MaterialIssueLine::query()->create([
                    'material_issue_id' => $issue->id,
                    'material_id' => $materialId,
                    'required_qty' => round($need, 3),
                    'issued_qty' => 0,
                    'status' => 'pending',
                ]);
            }

            return $issue;
        });
    }

    /**
     * 逐行发料。
     *
     * @param  array<int, float|string>  $qtyByLineId  行 id => 本次实发数量
     * @return array{issued:int, partial:int, pending:int, shortage:array<int, array{code:string, name:string, want:float, stock:float}>}
     */
    public function issue(MaterialIssue $issue, array $qtyByLineId, ?int $userId = null): array
    {
        if ($issue->status === 'issued') {
            throw new RuntimeException('这张领料单已经发齐了，不用再发。');
        }

        $result = ['issued' => 0, 'partial' => 0, 'pending' => 0, 'shortage' => []];

        DB::connection('mes')->transaction(function () use ($issue, $qtyByLineId, $userId, &$result) {
            $issue->load('lines.material');

            foreach ($issue->lines as $line) {
                $want = (float) ($qtyByLineId[$line->id] ?? 0);
                if ($want <= 0) {
                    continue;
                }

                // 不允许超发：最多把这一行发满
                $remain = (float) $line->required_qty - (float) $line->issued_qty;
                $qty = min($want, max(0, $remain));
                if ($qty <= 0) {
                    continue;
                }

                $material = $line->material;
                if (! $material) {
                    continue;
                }

                if ((float) $material->stock_qty < $qty) {
                    // 库存不够：这一行整行不发，记进缺料清单让计划员看到
                    $result['shortage'][] = [
                        'code' => (string) $material->code,
                        'name' => (string) $material->name,
                        'want' => round($qty, 3),
                        'stock' => (float) $material->stock_qty,
                    ];

                    continue;
                }

                $material->decrement('stock_qty', $qty);

                $issuedTotal = (float) $line->issued_qty + $qty;
                $line->update([
                    'issued_qty' => round($issuedTotal, 3),
                    'status' => $issuedTotal >= (float) $line->required_qty ? 'issued' : 'partial',
                ]);
            }

            // 重新汇总单状态
            $lines = $issue->fresh()->load('lines');
            $allIssued = $lines->lines->every(fn (MaterialIssueLine $l) => $l->status === 'issued');
            $anyIssued = $lines->lines->contains(fn (MaterialIssueLine $l) => (float) $l->issued_qty > 0);

            $issue->status = $allIssued ? 'issued' : ($anyIssued ? 'partial' : 'draft');
            if ($anyIssued) {
                $issue->issued_at = $issue->issued_at ?? now();
                $issue->issued_by = $userId;
            }
            $issue->save();

            $result['issued'] = $lines->lines->where('status', 'issued')->count();
            $result['partial'] = $lines->lines->where('status', 'partial')->count();
            $result['pending'] = $lines->lines->where('status', 'pending')->count();
        });

        return $result;
    }

    /** MI-2026-0001 格式，按年自增。 */
    public function nextIssueNo(): string
    {
        $prefix = 'MI-' . now()->format('Y') . '-';
        $last = MaterialIssue::query()
            ->where('issue_no', 'like', $prefix . '%')
            ->orderByDesc('issue_no')
            ->value('issue_no');

        $seq = $last ? ((int) substr((string) $last, -4)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** 一张领料单的汇总（给列表/详情页用）。 */
    public function summary(MaterialIssue $issue): array
    {
        $lines = $issue->lines;
        $required = (float) $lines->sum('required_qty');
        $issued = (float) $lines->sum('issued_qty');
        $amount = 0.0;
        foreach ($lines as $line) {
            $amount += (float) ($line->material?->std_cost ?? 0) * (float) $line->required_qty;
        }

        return [
            'lines' => $lines->count(),
            'required' => round($required, 3),
            'issued' => round($issued, 3),
            'rate' => $required > 0 ? round($issued / $required * 100, 1) : 0.0,
            'amount' => round($amount, 2),
        ];
    }
}
