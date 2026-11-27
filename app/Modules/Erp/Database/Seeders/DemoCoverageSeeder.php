<?php

declare(strict_types=1);

namespace App\Modules\Erp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 演示数据覆盖种子（第一批：销售 → 采购 → 生产 → 库存 → 质量 主干链路）。
 *
 * 目标：让 ERP 每个业务列表页都有可点开的数据，且单据状态覆盖全流程。
 * 幂等：每段数据用 DM 前缀业务编号，已存在即跳过，可重复执行。
 * 只写数据库预置的虚拟演示数据，不含真实企业信息。
 */
class DemoCoverageSeeder extends Seeder
{
    private string $conn = 'erp';

    /** 演示单价/成本参考 */
    private array $priceOf = [
        1 => 0.0035, 2 => 0.004, 3 => 8.5, 4 => 0.35, 5 => 0.12,
        6 => 3.2, 7 => 0.015, 8 => 4.8, 9 => 22, 10 => 35,
        11 => 2, 12 => 1, 13 => 0.5, 14 => 30, 15 => 30,
    ];

    public function run(): void
    {
        $this->seedSalesQuotes();
        $this->seedSalesOrders();
        $this->seedShipments();
        $this->seedCustomerReturns();
        $this->seedPurchaseRequisitions();
        $this->seedPurchaseOrders();
        $this->seedGoodsReceipts();
        $this->seedProductionOrders();
        $this->seedMaterialIssues();
        $this->seedOperationLogs();
        $this->seedStocktakes();
        $this->seedInventoryAdjustments();
        $this->seedInspectionTemplates();
        $this->seedInspectionRecords();
        $this->seedCorrectiveActions();
        $this->seedInventoryStatusChanges();
        $this->seedBomImports();
        $this->seedApprovals();
        $this->seedSystemMessages();
    }

    // ---------------------------------------------------------------- 工具

    /** 按表实际列裁剪后插入，避免列名不一致导致整段失败 */
    private function insert(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        if (! Schema::connection($this->conn)->hasTable($table)) {
            return;
        }
        $cols = Schema::connection($this->conn)->getColumnListing($table);
        $clean = [];
        foreach ($rows as $row) {
            $r = array_intersect_key($row, array_flip($cols));
            if (array_key_exists('id', $r) && $r['id'] === null) {
                unset($r['id']);
            }
            $clean[] = $r;
        }
        try {
            DB::connection($this->conn)->table($table)->insert($clean);
        } catch (\Illuminate\Database\QueryException $e) {
            echo "  [跳过] {$table}: " . mb_substr($e->getMessage(), 0, 160) . PHP_EOL;
        }
    }

    private function count(string $table): int
    {
        return Schema::connection($this->conn)->hasTable($table)
            ? DB::connection($this->conn)->table($table)->count()
            : 0;
    }

    private function has(string $table, string $col, string $value): bool
    {
        return Schema::connection($this->conn)->hasTable($table)
            && DB::connection($this->conn)->table($table)->where($col, $value)->exists();
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function day(int $offsetDays): string
    {
        return now()->addDays($offsetDays)->format('Y-m-d');
    }

    private function ts(int $offsetDays, string $time = '09:00:00'): string
    {
        return now()->addDays($offsetDays)->format('Y-m-d') . ' ' . $time;
    }

    /** 取某个物料的单价（缺省给 1） */
    private function price(int $materialId): float
    {
        return $this->priceOf[$materialId] ?? 1.0;
    }

    /** 客户列表（演示虚拟公司名） */
    private array $customers = [1, 2, 3, 4];

    private array $suppliers = [1, 2, 3, 4, 5, 6];

    private array $warehouses = [1, 2, 3];

    // ---------------------------------------------------------------- 销售

    private function seedSalesQuotes(): void
    {
        if ($this->has('sales_quotes', 'quote_no', 'SQ-DM-001')) {
            return;
        }

        $quotes = [
            ['SQ-DM-001', 1, -60, -30, 'accepted', [[10, 200], [3, 500]], '年度框架报价，按季度调价'],
            ['SQ-DM-002', 2, -45, -15, 'accepted', [[9, 300], [4, 2000]], 'PCBA 批量报价'],
            ['SQ-DM-003', 3, -30, 0, 'sent', [[10, 100]], '客户正在比价'],
            ['SQ-DM-004', 4, -20, 10, 'draft', [[14, 50], [15, 50]], '新项目打样报价'],
            ['SQ-DM-005', 1, -10, 20, 'rejected', [[6, 100], [7, 5000]], '价格未达成一致'],
        ];

        $ts = $this->now();
        foreach ($quotes as [$no, $cust, $date, $valid, $status, $lines, $remark]) {
            $amount = 0;
            foreach ($lines as [$m, $q]) {
                $amount += $q * $this->price($m);
            }
            $qid = $this->insertReturningId('sales_quotes', [
                'quote_no' => $no,
                'customer_id' => $cust,
                'quote_date' => $this->day($date),
                'valid_until' => $this->day($valid),
                'status' => $status,
                'total_amount' => round($amount, 2),
                'remark' => $remark,
                'created_by' => 5,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);

            $rows = [];
            foreach ($lines as $i => [$m, $q]) {
                $rows[] = [
                    'quote_id' => $qid,
                    'material_id' => $m,
                    'qty' => $q,
                    'unit_price' => $this->price($m),
                    'delivery_days' => 15 + $i * 5,
                    'remark' => null,
                ];
            }
            $this->insert('sales_quote_lines', $rows);
        }
    }

    private function seedSalesOrders(): void
    {
        if ($this->has('sales_orders', 'order_no', 'SO-DM-001')) {
            return;
        }

        $orders = [
            ['SO-DM-001', 1, -40, -25, 'completed', [[10, 200], [3, 500]], '客户 A 年度订单'],
            ['SO-DM-002', 2, -30, -10, 'shipped', [[9, 300], [4, 2000]], 'PCBA 批量出货'],
            ['SO-DM-003', 3, -20, 5, 'confirmed', [[10, 100]], '等待排产'],
            ['SO-DM-004', 4, -15, 10, 'in_production', [[14, 50], [15, 50]], '新项目首批'],
            ['SO-DM-005', 1, -8, 20, 'confirmed', [[6, 100]], '外壳补单'],
            ['SO-DM-006', 2, -3, 25, 'draft', [[10, 50]], '客户追加，待确认'],
        ];

        $ts = $this->now();
        foreach ($orders as [$no, $cust, $date, $delivery, $status, $lines, $remark]) {
            $amount = 0;
            foreach ($lines as [$m, $q]) {
                $amount += $q * $this->price($m);
            }
            $shipped = in_array($status, ['shipped', 'completed'], true) ? array_sum(array_column($lines, 1)) : 0;

            $oid = $this->insertReturningId('sales_orders', [
                'order_no' => $no,
                'customer_id' => $cust,
                'quote_id' => null,
                'order_date' => $this->day($date),
                'delivery_date' => $this->day($delivery),
                'status' => $status,
                'total_amount' => round($amount, 2),
                'shipped_qty' => $shipped,
                'remark' => $remark,
                'salesperson_id' => 5,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);

            $rows = [];
            foreach ($lines as [$m, $q]) {
                $rows[] = [
                    'order_id' => $oid,
                    'material_id' => $m,
                    'qty' => $q,
                    'shipped_qty' => $shipped > 0 ? $q : 0,
                    'unit_price' => $this->price($m),
                    'remark' => null,
                    'std_cost_amount' => round($q * $this->price($m) * 0.7, 2),
                ];
            }
            $this->insert('sales_order_lines', $rows);
        }
    }

    private function seedShipments(): void
    {
        if ($this->has('shipments', 'shipment_no', 'SH-DM-001')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['SH-DM-001', 1, 1, -25, '顺丰速运', 'SF-DEMO-0001', 'delivered', 700],
            ['SH-DM-002', 2, 2, -10, '德邦物流', 'DB-DEMO-0002', 'delivered', 2300],
            ['SH-DM-003', 3, 4, -2, '中通快运', 'ZT-DEMO-0003', 'in_transit', 100],
        ];
        foreach ($list as [$no, $soId, $cust, $date, $carrier, $tracking, $status, $qty]) {
            $this->insert('shipments', [[
                'shipment_no' => $no,
                'sales_order_id' => $soId,
                'customer_id' => $cust,
                'shipment_date' => $this->day($date),
                'logistics_company' => $carrier,
                'tracking_no' => $tracking,
                'status' => $status,
                'total_qty' => $qty,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);
        }
    }

    private function seedCustomerReturns(): void
    {
        if ($this->has('customer_returns', 'return_no', 'CR-DM-001')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['CR-DM-001', 1, 1, '外观划伤，客户要求换货', 'approved', 20],
            ['CR-DM-002', 2, 2, '功能不良，批次性虚焊', 'processing', 30],
            ['CR-DM-003', 3, 4, '包装破损', 'pending', 5],
        ];
        foreach ($list as [$no, $soId, $cust, $reason, $status, $qty]) {
            $rid = $this->insertReturningId('customer_returns', [
                'return_no' => $no,
                'sales_order_id' => $soId,
                'customer_id' => $cust,
                'return_reason' => $reason,
                'status' => $status,
                'total_qty' => $qty,
                'created_by' => 5,
                'approved_by' => $status === 'approved' ? 6 : null,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);

            $this->insert('customer_return_lines', [[
                'return_id' => $rid,
                'material_id' => 10,
                'lot_no' => 'LOT20261101',
                'qty' => $qty,
                'disposition' => $status === 'approved' ? 'rework' : 'pending',
                'disposition_qty' => $status === 'approved' ? $qty : 0,
                'handled_at' => $status === 'approved' ? $this->ts(-2) : null,
            ]]);
        }
    }

    // ---------------------------------------------------------------- 采购

    private function seedPurchaseRequisitions(): void
    {
        if ($this->has('purchase_requisitions', 'req_no', 'PR-DM-001')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['PR-DM-001', 'Q4 贴片电阻安全库存补充', 1, 8, 5, 'approved', [[1, 20000]]],
            ['PR-DM-002', '新项目 MCU 首批采购', 1, 9, 10, 'approved', [[3, 2000], [8, 500]]],
            ['PR-DM-003', '包装耗材月度补货', 3, 7, 7, 'pending', [[13, 10000]]],
            ['PR-DM-004', '连接器急件采购', 1, 8, 2, 'rejected', [[4, 5000]]],
        ];

        foreach ($list as [$no, $title, $dept, $requester, $needDays, $status, $lines]) {
            $rid = $this->insertReturningId('purchase_requisitions', [
                'req_no' => $no,
                'title' => $title,
                'department_id' => $dept,
                'requester_id' => $requester,
                'need_date' => $this->day($needDays),
                'status' => $status,
                'remark' => null,
                'approver_id' => $status === 'approved' ? 4 : null,
                'approved_at' => $status === 'approved' ? $this->ts(-3) : null,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);

            $rows = [];
            foreach ($lines as [$m, $q]) {
                $rows[] = [
                    'requisition_id' => $rid,
                    'material_id' => $m,
                    'qty' => $q,
                    'unit' => 'PCS',
                    'estimated_price' => $this->price($m),
                    'remark' => null,
                ];
            }
            $this->insert('purchase_requisition_lines', $rows);
        }
    }

    private function seedPurchaseOrders(): void
        {
            if ($this->has('purchase_orders', 'po_no', 'PO-DM-001')) {
                return;
            }

            $ts = $this->now();
            $list = [
                ['PO-DM-001', 1, 4, -35, -25, 'completed', [[1, 20000], [2, 10000]]],
                ['PO-DM-002', 3, 4, -28, -15, 'received', [[3, 2000], [8, 500]]],
                ['PO-DM-003', 2, 4, -18, -5, 'confirmed', [[6, 500], [7, 20000]]],
                ['PO-DM-004', 4, 4, -10, 5, 'confirmed', [[5, 3000]]],
                ['PO-DM-005', 5, 4, -4, 12, 'draft', [[9, 200]]],
            ];

            foreach ($list as [$no, $sup, $buyer, $date, $expected, $status, $lines]) {
                $amount = 0;
                foreach ($lines as [$m, $q]) {
                    $amount += $q * $this->price($m);
                }
                $received = in_array($status, ['completed', 'received'], true) ? array_sum(array_column($lines, 1)) : 0;

                $poId = $this->insertReturningId('purchase_orders', [
                    'po_no' => $no,
                    'supplier_id' => $sup,
                    'buyer_id' => $buyer,
                    'order_date' => $this->day($date),
                    'expected_date' => $this->day($expected),
                    'status' => $status,
                    'total_amount' => round($amount, 2),
                    'received_qty' => $received,
                    'remark' => '演示采购单',
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);

                $rows = [];
                foreach ($lines as [$m, $q]) {
                    $rows[] = [
                        'po_id' => $poId,
                        'material_id' => $m,
                        'qty' => $q,
                        'received_qty' => $received > 0 ? $q : 0,
                        'unit_price' => $this->price($m),
                        'remark' => null,
                    ];
                }
                $this->insert('purchase_order_lines', $rows);
            }
        }

    private function seedGoodsReceipts(): void
    {
        if ($this->has('goods_receipts', 'receipt_no', 'GR-DM-001')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['GR-DM-001', 1, 1, -25, 1, 'completed', 30000, 30000],
            ['GR-DM-002', 2, 3, -15, 1, 'completed', 2500, 2300],
            ['GR-DM-003', 3, 2, -5, 1, 'inspecting', 20500, 0],
        ];

        foreach ($list as [$no, $poId, $sup, $date, $wh, $status, $totalQty, $qualified]) {
            $rid = $this->insertReturningId('goods_receipts', [
                'receipt_no' => $no,
                'po_id' => $poId,
                'supplier_id' => $sup,
                'receipt_date' => $this->day($date),
                'warehouse_id' => $wh,
                'status' => $status,
                'total_qty' => $totalQty,
                'receiver_id' => 9,
                'department_id' => 1,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);

            $rows = [];
            $materials = $poId === 1 ? [[1, 20000], [2, 10000]] : ($poId === 3 ? [[3, 2000], [8, 500]] : [[6, 500], [7, 20000]]);
            foreach ($materials as $i => [$m, $q]) {
                $rows[] = [
                    'receipt_id' => $rid,
                    'material_id' => $m,
                    'lot_no' => 'LOT' . date('Ymd') . sprintf('%02d', $i + 1),
                    'qty' => $q,
                    'qualified_qty' => $status === 'completed' ? (int) round($q * 0.97) : 0,
                    'rejected_qty' => $status === 'completed' ? $q - (int) round($q * 0.97) : 0,
                    'supplier_batch' => 'B' . date('Ymd'),
                ];
            }
            $this->insert('goods_receipt_lines', $rows);
        }
    }

    // ---------------------------------------------------------------- 生产

    private function seedProductionOrders(): void
    {
        if ($this->has('production_orders', 'order_no', 'WO-DM-001')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['WO-DM-001', 10, 1, 1, 200, 200, 8, 'completed', -30, -20, 1],
            ['WO-DM-002', 9, 1, 1, 300, 250, 6, 'in_progress', -10, 5, 2],
            ['WO-DM-003', 14, 2, 1, 50, 0, 0, 'released', -3, 10, 4],
            ['WO-DM-004', 15, 2, 1, 50, 0, 0, 'planned', 2, 15, 4],
        ];

        foreach ($list as [$no, $product, $bom, $routing, $plan, $done, $bad, $status, $start, $end, $soId]) {
            $this->insert('production_orders', [[
                'order_no' => $no,
                'product_material_id' => $product,
                'bom_id' => $bom,
                'routing_id' => $routing,
                'plan_qty' => $plan,
                'completed_qty' => $done,
                'defective_qty' => $bad,
                'status' => $status,
                'start_date' => $this->day($start),
                'end_date' => $this->day($end),
                'sales_order_id' => $soId,
                'remark' => '演示生产工单',
                'created_at' => $ts,
                'updated_at' => $ts,
                'department_id' => 1,
                'created_by' => 8,
            ]]);
        }
    }

    private function seedMaterialIssues(): void
    {
        if ($this->has('material_issues', 'issue_no', 'MI-DM-001')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['MI-DM-001', 1, 1, 'completed', -22, [[11, 400], [12, 200]]],
            ['MI-DM-002', 2, 1, 'completed', -6, [[1, 60000], [2, 30000]]],
            ['MI-DM-003', 3, 1, 'pending', 0, [[11, 100], [12, 50]]],
        ];

        foreach ($list as [$no, $woId, $wh, $status, $date, $lines]) {
            $iid = $this->insertReturningId('material_issues', [
                'issue_no' => $no,
                'production_order_id' => $woId,
                'warehouse_id' => $wh,
                'status' => $status,
                'issue_date' => $this->day($date),
                'created_by' => 9,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);

            $rows = [];
            foreach ($lines as [$m, $q]) {
                $rows[] = [
                    'material_issue_id' => $iid,
                    'material_id' => $m,
                    'lot_no' => 'LOT20261101',
                    'qty' => $q,
                ];
            }
            $this->insert('material_issue_lines', $rows);
        }

        // 领料申请单（含超 BOM 申请，便于演示审批）
        if (! $this->has('material_issue_requests', 'request_no', 'MIR-DM-001')) {
            $req = [
                ['MIR-DM-001', 2, '工单补料：贴片电阻抛料', 'approved', 1, 9, 8, [[1, 5000, 4000, 1]]],
                ['MIR-DM-002', 3, '工单备料', 'pending', 0, 9, null, [[11, 100, 100, 0], [12, 50, 50, 0]]],
                ['MIR-DM-003', 1, '超领申请：返工用料', 'rejected', 1, 9, 8, [[12, 300, 200, 1]]],
            ];
            foreach ($req as [$no, $wo, $purpose, $status, $over, $by, $approver, $lines]) {
                $rid = $this->insertReturningId('material_issue_requests', [
                    'request_no' => $no,
                    'production_order_id' => $wo,
                    'purpose' => $purpose,
                    'status' => $status,
                    'has_over_bom' => $over,
                    'requested_by' => $by,
                    'approver_id' => $approver,
                    'approved_at' => $status === 'approved' ? $this->ts(-1) : null,
                    'reject_reason' => $status === 'rejected' ? '超领比例过高，请核实损耗' : null,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]);

                $rows = [];
                foreach ($lines as [$m, $q, $std, $isOver]) {
                    $rows[] = [
                        'request_id' => $rid,
                        'material_id' => $m,
                        'qty' => $q,
                        'standard_qty' => $std,
                        'is_over_bom' => $isOver,
                    ];
                }
                $this->insert('material_issue_request_lines', $rows);
            }
        }
    }

    private function seedOperationLogs(): void
    {
        if ($this->count('production_operation_logs') > 0) {
            return;
        }

        $ts = $this->now();
        $rows = [];
        $ops = ['SMT 贴片', '回流焊', '插件装配', '功能测试', '包装'];
        for ($i = 0; $i < 10; $i++) {
            $wo = $i < 6 ? 1 : 2;
            $rows[] = [
                'production_order_id' => $wo,
                'operation_name' => $ops[$i % 5],
                'worker_id' => 10 + ($i % 3),
                'good_qty' => 40 + $i * 2,
                'defective_qty' => $i % 3,
                'defect_code' => $i % 3 === 0 ? 'D-SOLDER' : null,
                'work_hours' => 2 + ($i % 3) * 0.5,
                'reported_at' => $this->ts(-20 + $i, '16:30:00'),
                'remark' => null,
            ];
        }
        $this->insert('production_operation_logs', $rows);
    }

    private function seedStocktakes(): void
    {
        if ($this->has('stocktakes', 'stocktake_no', 'ST-DM-001')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['ST-DM-001', 1, 'finished', -12, -11],
            ['ST-DM-002', 1, 'counting', -1, null],
            ['ST-DM-003', 2, 'planned', 5, null],
        ];
        foreach ($list as [$no, $wh, $status, $plan, $finish]) {
            $sid = $this->insertReturningId('stocktakes', [
                'stocktake_no' => $no,
                'warehouse_id' => $wh,
                'status' => $status,
                'plan_date' => $this->day($plan),
                'finish_date' => $finish === null ? null : $this->day($finish),
                'created_by' => 9,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);

            $rows = [];
            foreach ([1, 2, 3] as $i => $m) {
                $book = 5000 * ($i + 1);
                $actual = $status === 'finished' ? $book - 20 : 0;
                $rows[] = [
                    'stocktake_id' => $sid,
                    'material_id' => $m,
                    'lot_no' => 'LOT20261101',
                    'book_qty' => $book,
                    'actual_qty' => $actual,
                    'diff_qty' => $status === 'finished' ? $actual - $book : 0,
                ];
            }
            $this->insert('stocktake_lines', $rows);
        }
    }

    private function seedInventoryAdjustments(): void
    {
        if ($this->has('inventory_adjustments', 'adjustment_no', 'IA-DM-001')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['IA-DM-001', 'stocktake', 1, 4, 'approved', 5000, 4980, '盘亏 20 颗，已审批调整'],
            ['IA-DM-002', 'stocktake', 1, 5, 'pending', 10000, 9990, '盘点差异待审批'],
            ['IA-DM-003', 'manual', 1, 6, 'approved', 300, 295, '车间损耗补登'],
        ];
        foreach ($list as [$no, $source, $wh, $m, $status, $before, $after, $reason]) {
            $this->insert('inventory_adjustments', [[
                'adjustment_no' => $no,
                'source' => $source,
                'stock_id' => null,
                'stocktake_id' => 1,
                'stocktake_line_id' => null,
                'material_id' => $m,
                'lot_no' => 'LOT20261101',
                'warehouse_id' => $wh,
                'location_id' => null,
                'status' => $status,
                'before_qty' => $before,
                'after_qty' => $after,
                'delta_qty' => $after - $before,
                'reason' => $reason,
            ]]);
        }
    }

    // ---------------------------------------------------------------- 质量

    private function seedInspectionTemplates(): void
    {
        if ($this->count('inspection_templates') > 0) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['IQC-STD-01', '来料检验标准（电子元器件）', 'iqc', '外观/尺寸/电性能/包装', '按 GB/T 2828.1 抽样，AQL 1.0', 'active'],
            ['IPQC-STD-01', '过程检验标准（SMT 贴片）', 'ipqc', '焊点/偏移/立碑/连锡', '每 2 小时抽 5 pcs，无连锡缺陷', 'active'],
            ['FQC-STD-01', '成品检验标准（智能时钟）', 'fqc', '功能/外观/包装/丝印', '全检功能项，外观 AQL 0.65', 'active'],
            ['OQC-STD-01', '出货检验标准', 'oqc', '包装/标签/数量', '按出货批次抽 10%', 'inactive'],
        ];
        foreach ($list as [$code, $name, $stage, $items, $std, $status]) {
            $this->insert('inspection_templates', [[
                'code' => $code,
                'name' => $name,
                'stage' => $stage,
                'check_items' => $items,
                'standard' => $std,
                'status' => $status,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);
        }

        if ($this->count('defects') === 0) {
            $defects = [
                ['D-SOLDER', '虚焊', '焊接', '焊点未完全熔接，存在冷焊'],
                ['D-BRIDGE', '连锡', '焊接', '相邻焊盘间短路'],
                ['D-TOMB', '立碑', '焊接', '元件一端翘起'],
                ['D-SHIFT', '偏移', '贴装', '贴装位置超出公差'],
                ['D-MISS', '漏件', '贴装', 'BOM 要求的元件未贴装'],
                ['D-SCRATCH', '外观划伤', '外观', '外壳表面划痕长度 > 3mm'],
                ['D-PACK', '包装破损', '包装', '彩盒变形或破损'],
                ['D-FUNC', '功能不良', '功能', '上电无显示或按键失灵'],
            ];
            foreach ($defects as [$code, $name, $cat, $desc]) {
                $this->insert('defects', [[
                    'code' => $code,
                    'name' => $name,
                    'category' => $cat,
                    'description' => $desc,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]]);
            }
        }
    }

    private function seedInspectionRecords(): void
    {
        if ($this->has('inspection_records', 'record_no', 'IR-DM-001')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['IR-DM-001', 'iqc', 1, 1, 'purchase', 50, 49, 1, 'passed', -24],
            ['IR-DM-002', 'iqc', 3, 2, 'purchase', 30, 27, 3, 'failed', -14],
            ['IR-DM-003', 'ipqc', 10, 1, 'production', 5, 5, 0, 'passed', -12],
            ['IR-DM-004', 'ipqc', 10, 2, 'production', 5, 4, 1, 'passed', -8],
            ['IR-DM-005', 'fqc', 10, 1, 'production', 20, 20, 0, 'passed', -5],
            ['IR-DM-006', 'fqc', 9, 2, 'production', 20, 17, 3, 'failed', -2],
            ['IR-DM-007', 'oqc', 10, 1, 'shipment', 10, 10, 0, 'passed', -1],
        ];
        foreach ($list as [$no, $stage, $m, $refId, $refType, $sample, $ok, $ng, $result, $date]) {
            $this->insert('inspection_records', [[
                'record_no' => $no,
                'stage' => $stage,
                'material_id' => $m,
                'ref_id' => $refId,
                'ref_type' => $refType,
                'sample_qty' => $sample,
                'qualified_qty' => $ok,
                'defect_qty' => $ng,
                'result' => $result,
                'inspector_id' => 12,
                'remark' => $result === 'failed' ? '已开纠正措施' : null,
                'inspected_at' => $this->ts($date, '14:00:00'),
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);
        }
    }

    private function seedCorrectiveActions(): void
    {
        if ($this->has('corrective_actions', 'ca_no', 'CA-DM-001')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['CA-DM-001', 2, '来料电容容值超差', '供应商分选精度不足', '要求供应商加严分选并提供报告', 12, 'in_progress', 5],
            ['CA-DM-002', 6, 'FQC 功能不良率 15%', '回流焊温区设定偏低', '调整温区曲线并做首件确认', 3, 'closed', -1],
            ['CA-DM-003', 4, 'IPQC 发现立碑', '贴装压力参数漂移', '重新校准贴片机压力', 8, 'open', 2],
        ];
        foreach ($list as [$no, $recId, $problem, $root, $action, $dueDays, $status, $owner]) {
            $this->insert('corrective_actions', [[
                'ca_no' => $no,
                'inspection_record_id' => $recId,
                'problem' => $problem,
                'root_cause' => $root,
                'action' => $action,
                'owner_id' => $owner,
                'due_date' => $this->day($dueDays),
                'status' => $status,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);
        }
    }

    private function seedInventoryStatusChanges(): void
    {
        if ($this->count('inventory_status_changes') > 0) {
            return;
        }

        $ts = $this->now();
        $list = [
            [1, 'LOT20261101', 'available', 'reserved', 500, '销售订单 SO-DM-003 锁库', 'APV-2026-001', -20],
            [1, 'LOT20261101', 'reserved', 'available', 200, '订单取消释放', 'APV-2026-002', -18],
            [2, 'LOT20261101', 'available', 'frozen', 300, '来料检验不合格冻结', 'APV-2026-003', -14],
            [3, 'LOT20261102', 'available', 'reserved', 100, '生产领料预占', 'APV-2026-004', -5],
        ];
        foreach ($list as [$m, $lot, $from, $to, $qty, $reason, $apv, $date]) {
            $this->insert('inventory_status_changes', [[
                'material_id' => $m,
                'lot_no' => $lot,
                'from_status' => $from,
                'to_status' => $to,
                'qty' => $qty,
                'reason' => $reason,
                'approval_no' => $apv,
                'operator_id' => 9,
                'approver_id' => 8,
                'created_at' => $this->ts($date, '10:00:00'),
            ]]);
        }
    }

    private function seedBomImports(): void
    {
        $ts = $this->now();

        if (! $this->has('bom_imports', 'import_no', 'BI-DM-001')) {
            $list = [
                ['BI-DM-001', '智能时钟_BOM_V2.xlsx', 10, 30, 28, 2, 'completed'],
                ['BI-DM-002', 'PCBA_BOM_试产.xlsx', 9, 45, 45, 0, 'completed'],
                ['BI-DM-003', '新项目_BOM_待确认.xlsx', 14, 60, 0, 0, 'parsing'],
            ];
            foreach ($list as [$no, $file, $product, $total, $ok, $err, $status]) {
                $this->insert('bom_imports', [[
                    'import_no' => $no,
                    'file_name' => $file,
                    'file_path' => 'storage/app/demo/' . $no . '.xlsx',
                    'product_material_id' => $product,
                    'total_rows' => $total,
                    'success_rows' => $ok,
                    'error_rows' => $err,
                    'status' => $status,
                    'created_by' => 8,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ]]);
            }
        }

        // 临时行表：按导入任务逐条补齐（独立幂等，便于单独重跑）
        if ($this->count('bom_import_rows') === 0) {
            $imports = DB::connection($this->conn)->table('bom_imports')
                ->where('import_no', 'like', 'BI-DM-%')
                ->orderBy('id')
                ->get();

            foreach ($imports as $imp) {
                $rows = [];
                $errRows = (int) $imp->error_rows;
                for ($i = 1; $i <= min(6, (int) $imp->total_rows); $i++) {
                    $matched = $i <= 3;
                    $rows[] = [
                        'import_id' => $imp->id,
                        'row_no' => $i,
                        'raw_mpn' => 'DM-MPN-' . sprintf('%04d', $i),
                        'raw_name' => '演示元件 ' . $i,
                        'raw_spec' => '0402 ±1%',
                        'raw_qty' => (string) $i,
                        'raw_ref_des' => 'R' . (10 + $i),
                        'raw_manufacturer' => '演示厂商',
                        'matched_material_id' => $matched ? $i : null,
                        'match_status' => $matched ? 'matched' : 'unmatched',
                        'action' => $matched ? 'import' : 'new_material',
                        'new_material_request_id' => null,
                        'error_message' => $matched ? null : '物料库中不存在该 MPN',
                    ];
                }
                $this->insert('bom_import_rows', $rows);
            }
        }

        if ($this->count('bom_column_templates') === 0) {
            $this->insert('bom_column_templates', [
                ['template_name' => '标准 BOM 列映射', 'mpn_column' => 'MPN', 'qty_column' => 'QTY', 'spec_column' => 'SPEC', 'refdes_column' => 'REFDES', 'manufacturer_column' => 'MANUFACTURER', 'name_column' => 'NAME', 'is_default' => 1],
                ['template_name' => '代理 BOM 列映射', 'mpn_column' => 'Part Number', 'qty_column' => 'Qty', 'spec_column' => 'Description', 'refdes_column' => 'Designator', 'manufacturer_column' => 'Mfr', 'name_column' => 'Item', 'is_default' => 0],
            ]);
        }
    }

    // ---------------------------------------------------------------- 审批与消息

    private function seedApprovals(): void
    {
        if ($this->has('approval_instances', 'business_type', 'sales_quote')) {
            return;
        }

        $ts = $this->now();
        $list = [
            ['sales_quote', 1, '采购申请 PR-DM-001', 'approved', 2, -30],
            ['purchase_requisition', 1, '采购申请 PR-DM-002', 'pending', 1, -5],
            ['material_issue_request', 1, '超领申请 MIR-DM-001', 'approved', 3, -1],
            ['customer_return', 1, '客户退货 CR-DM-001', 'pending', 1, 0],
            ['expense', 1, '费用报销（演示）', 'rejected', 2, -3],
        ];
        foreach ($list as [$type, $bizId, $title, $status, $step, $date]) {
            $iid = $this->insertReturningId('approval_instances', [
                'flow_id' => 1,
                'business_type' => $type,
                'business_id' => $bizId,
                'current_step' => $step,
                'status' => $status,
                'applicant_id' => 8,
                'logs_json' => json_encode([
                    ['step' => 1, 'approver' => 8, 'action' => 'submit', 'at' => $this->ts($date, '09:30:00'), 'note' => $title],
                    ['step' => 2, 'approver' => 6, 'action' => $status, 'at' => $this->ts($date + 1, '10:00:00'), 'note' => '演示审批意见'],
                ], JSON_UNESCAPED_UNICODE),
                'payload_json' => json_encode(['title' => $title, 'amount' => 12800], JSON_UNESCAPED_UNICODE),
                'delegated' => 0,
                'approved_at' => $status === 'approved' ? $this->ts($date + 1) : null,
                'created_at' => $this->ts($date, '09:30:00'),
                'updated_at' => $ts,
            ]);

            $this->insert('approval_tasks', [[
                'approval_instance_id' => $iid,
                'approver_id' => 6,
                'status' => $status === 'approved' ? 'approved' : ($status === 'rejected' ? 'rejected' : 'pending'),
                'comment' => '演示审批意见',
                'acted_at' => $status === 'pending' ? null : $this->ts($date + 1, '10:00:00'),
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);
        }
    }

    private function seedSystemMessages(): void
    {
        $ts = $this->now();

        if ($this->count('system_notifications') === 0) {
            $rows = [];
            $items = [
                ['approval', '待审批：采购申请 PR-DM-002', 'purchase_requisition', 1],
                ['order', '销售订单 SO-DM-003 已确认，请安排排产', 'sales_order', 3],
                ['stock', '物料 MCU微控制器 低于安全库存', 'material', 3],
                ['quality', 'FQC 批次 IR-DM-006 判定不合格', 'inspection_record', 6],
                ['todo', '本周盘点任务待开始', 'stocktake', 3],
                ['system', '系统将于本周日凌晨进行维护', null, null],
            ];
            foreach ($items as $i => [$type, $title, $biz, $ref]) {
                $rows[] = [
                    'user_id' => $i % 2 === 0 ? 1 : 6,
                    'notify_type' => $type,
                    'title' => $title,
                    'content' => '演示通知内容：' . $title,
                    'is_read' => $i > 3 ? 1 : 0,
                    'business_type' => $biz,
                    'business_id' => $ref,
                    'created_at' => $this->ts(-6 + $i, '08:30:00'),
                ];
            }
            $this->insert('system_notifications', $rows);
        }

        if ($this->count('system_todo_items') === 0) {
            $rows = [];
            $items = [
                ['approval', '审批采购申请 PR-DM-002', 2],
                ['production', '确认工单 WO-DM-003 开工', 1],
                ['stocktake', '执行盘点单 ST-DM-002', 3],
                ['quality', '跟踪纠正措施 CA-DM-001', 7],
                ['purchase', '催办采购单 PO-DM-004 到货', 5],
                ['finance', '核对客户对账差异', -1],
            ];
            foreach ($items as $i => [$type, $title, $due]) {
                $rows[] = [
                    'user_id' => 1,
                    'todo_type' => $type,
                    'title' => $title,
                    'deadline' => $this->ts($due, '18:00:00'),
                    'is_finished' => 0,
                    'business_type' => $type,
                    'business_id' => $i + 1,
                    'created_at' => $this->ts(-5 + $i, '09:00:00'),
                    'finished_at' => null,
                ];
            }
            $this->insert('system_todo_items', $rows);
        }

        if ($this->count('system_chat_messages') === 0) {
            $rows = [];
            $texts = [
                [8, '工单 WO-DM-002 今日计划 300，目前完成 250。'],
                [9, '收到，仓库这边下午补发 60000 颗电阻。'],
                [6, '采购单 PO-DM-004 供应商反馈下周一到货。'],
                [12, 'FQC 批次 IR-DM-006 有 3 pcs 功能不良，已开纠正措施。'],
                [5, '销售订单 SO-DM-006 客户催交付，请评估排产。'],
                [8, '已把 WO-DM-003 提前到本周排产。'],
            ];
            foreach ($texts as $i => [$uid, $text]) {
                $rows[] = [
                    'room_id' => 1,
                    'sender_id' => $uid,
                    'msg_type' => 'text',
                    'content' => $text,
                    'file_id' => null,
                    'created_at' => $this->ts(-3, sprintf('%02d:15:00', 9 + $i)),
                    'deleted_at' => null,
                    'deleted_by' => null,
                ];
            }
            $this->insert('system_chat_messages', $rows);
        }
    }

    // ---------------------------------------------------------------- 辅助

    /** 插入一行并返回自增 id（SQLite/MySQL 通用） */
    private function insertReturningId(string $table, array $row): int
    {
        $this->insert($table, [$row]);

        $id = DB::connection($this->conn)->table($table)->max('id');

        return (int) $id;
    }
}
