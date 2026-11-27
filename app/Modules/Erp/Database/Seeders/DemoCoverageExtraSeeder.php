<?php

declare(strict_types=1);

namespace App\Modules\Erp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 演示数据覆盖种子（第二批：财务 / CRM / 人事 / 商品营销 / 平台事件 / 协同系统 / 研发项目 / 计划 / AI / 多租户）。
 *
 * 与 DemoCoverageSeeder 同一套幂等规则：先判重再插入，可重复执行。
 * 全部为虚拟演示数据。
 */
class DemoCoverageExtraSeeder extends Seeder
{
    private string $conn = 'erp';

    public function run(): void
    {
        $this->seedFinance();
        $this->seedCrm();
        $this->seedHr();
        $this->seedProductMarketing();
        $this->seedPlatformEvents();
        $this->seedCollab();
        $this->seedRnd();
        $this->seedPlanning();
        $this->seedAi();
        $this->seedTenantWecomVoice();
        $this->seedEcomShops();
    }

    // ---------------------------------------------------------------- 工具

    private function insert(string $table, array $rows): void
    {
        if ($rows === [] || ! Schema::connection($this->conn)->hasTable($table)) {
            return;
        }
        $cols = Schema::connection($this->conn)->getColumnListing($table);
        $clean = [];
        foreach ($rows as $row) {
            $clean[] = array_intersect_key($row, array_flip($cols));
        }
        try {
            DB::connection($this->conn)->table($table)->insert($clean);
        } catch (\Illuminate\Database\QueryException $e) {
            echo "  [跳过] {$table}: " . mb_substr($e->getMessage(), 0, 150) . PHP_EOL;
        }
    }

    private function count(string $table): int
    {
        return Schema::connection($this->conn)->hasTable($table)
            ? DB::connection($this->conn)->table($table)->count()
            : 0;
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    private function day(int $offset): string
    {
        return now()->addDays($offset)->format('Y-m-d');
    }

    private function ts(int $offset, string $time = '09:00:00'): string
    {
        return now()->addDays($offset)->format('Y-m-d') . ' ' . $time;
    }

    // ---------------------------------------------------------------- 财务

    private function seedFinance(): void
    {
        $ts = $this->now();

        if ($this->count('customer_invoices') === 0) {
            $list = [
                ['INV-DM-001', 1, 1, -30, -10, 2500, 325, 2500, 'paid'],
                ['INV-DM-002', 2, 2, -20, 5, 6600, 858, 3000, 'partial'],
                ['INV-DM-003', 3, 3, -10, 20, 1500, 195, 0, 'open'],
                ['INV-DM-004', 4, 4, -5, 25, 3000, 390, 0, 'open'],
                ['INV-DM-005', 1, 1, -60, -30, 1000, 130, 1000, 'paid'],
            ];
            $rows = [];
            foreach ($list as [$no, $cust, $soId, $date, $due, $amount, $tax, $paid, $status]) {
                $rows[] = [
                    'invoice_no' => $no,
                    'customer_id' => $cust,
                    'sales_order_id' => $soId,
                    'invoice_date' => $this->day($date),
                    'due_date' => $this->day($due),
                    'amount' => $amount,
                    'tax_amount' => $tax,
                    'paid_amount' => $paid,
                    'status' => $status,
                    'owner_id' => 5,
                    'salesperson_id' => 5,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('customer_invoices', $rows);
        }

        if ($this->count('supplier_bills') === 0) {
            $list = [
                ['BL-DM-001', 1, 1, -30, -10, 2100, 2100, 'paid'],
                ['BL-DM-002', 2, 3, -18, 12, 3200, 1000, 'partial'],
                ['BL-DM-003', 3, 4, -10, 20, 1800, 0, 'open'],
                ['BL-DM-004', 4, 5, -3, 27, 900, 0, 'open'],
            ];
            $rows = [];
            foreach ($list as [$no, $sup, $poId, $date, $due, $amount, $paid, $status]) {
                $rows[] = [
                    'bill_no' => $no,
                    'supplier_id' => $sup,
                    'purchase_order_id' => $poId,
                    'bill_date' => $this->day($date),
                    'due_date' => $this->day($due),
                    'amount' => $amount,
                    'paid_amount' => $paid,
                    'status' => $status,
                    'owner_id' => 7,
                    'buyer_id' => 4,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('supplier_bills', $rows);
        }

        if ($this->count('payments') === 0) {
            $list = [
                ['PAY-DM-001', 'in', 1, null, 2500, 'bank', -8, 'INV-DM-001', 'paid'],
                ['PAY-DM-002', 'in', 2, null, 3000, 'bank', -5, 'INV-DM-002', 'paid'],
                ['PAY-DM-003', 'out', null, 1, 2100, 'bank', -6, 'BL-DM-001', 'paid'],
                ['PAY-DM-004', 'out', null, 2, 1000, 'cash', -2, 'BL-DM-002', 'paid'],
                ['PAY-DM-005', 'in', 3, null, 800, 'alipay', 0, 'INV-DM-003', 'pending'],
            ];
            $rows = [];
            foreach ($list as [$no, $dir, $cust, $sup, $amount, $method, $date, $ref, $status]) {
                $rows[] = [
                    'payment_no' => $no,
                    'direction' => $dir,
                    'customer_id' => $cust,
                    'supplier_id' => $sup,
                    'amount' => $amount,
                    'method' => $method,
                    'paid_at' => $this->ts($date, '15:00:00'),
                    'ref_no' => $ref,
                    'user_id' => 7,
                    'status' => $status,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('payments', $rows);

            $this->insert('payment_allocations', [
                ['payment_id' => 1, 'invoice_id' => 1, 'bill_id' => null, 'amount' => 2500, 'remark' => '货款核销'],
                ['payment_id' => 2, 'invoice_id' => 2, 'bill_id' => null, 'amount' => 3000, 'remark' => '货款核销'],
                ['payment_id' => 3, 'invoice_id' => null, 'bill_id' => 1, 'amount' => 2100, 'remark' => '供应商付款核销'],
                ['payment_id' => 4, 'invoice_id' => null, 'bill_id' => 2, 'amount' => 1000, 'remark' => '供应商付款核销'],
            ]);
        }

        if ($this->count('financial_transactions') === 0) {
            $rows = [];
            $list = [
                ['TX-DM-001', '销售收款', 2500, 'in', '客户 A 货款', -8],
                ['TX-DM-002', '采购付款', 2100, 'out', '供应商 A 货款', -6],
                ['TX-DM-003', '工资发放', 86000, 'out', '11 月工资', -5],
                ['TX-DM-004', '水电费', 3200, 'out', '厂区水电', -4],
                ['TX-DM-005', '运费支出', 1800, 'out', '物流费', -3],
                ['TX-DM-006', '销售收款', 3000, 'in', '客户 B 货款', -2],
                ['TX-DM-007', '零星采购', 450, 'out', '办公用品', -1],
                ['TX-DM-008', '利息收入', 260, 'in', '银行利息', 0],
            ];
            foreach ($list as [$no, $cat, $amount, $dir, $summary, $date]) {
                $rows[] = [
                    'tx_no' => $no,
                    'category' => $cat,
                    'amount' => $amount,
                    'direction' => $dir,
                    'summary' => $summary,
                    'tx_date' => $this->day($date),
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('financial_transactions', $rows);
        }

        if ($this->count('finance_reconciles') === 0) {
            $this->insert('finance_reconciles', [
                ['reconcile_no' => 'RC-DM-001', 'period' => now()->format('Y-m'), 'type' => 'bank', 'book_amount' => 128600, 'actual_amount' => 128560, 'diff_amount' => 40, 'status' => 'diff', 'created_at' => $ts, 'updated_at' => $ts],
                ['reconcile_no' => 'RC-DM-002', 'period' => now()->subMonth()->format('Y-m'), 'type' => 'bank', 'book_amount' => 98600, 'actual_amount' => 98600, 'diff_amount' => 0, 'status' => 'matched', 'created_at' => $ts, 'updated_at' => $ts],
                ['reconcile_no' => 'RC-DM-003', 'period' => now()->format('Y-m'), 'type' => 'customer', 'book_amount' => 8600, 'actual_amount' => 8800, 'diff_amount' => -200, 'status' => 'diff', 'created_at' => $ts, 'updated_at' => $ts],
                ['reconcile_no' => 'RC-DM-004', 'period' => now()->subMonth()->format('Y-m'), 'type' => 'supplier', 'book_amount' => 5200, 'actual_amount' => 5200, 'diff_amount' => 0, 'status' => 'confirmed', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('finance_settles') === 0) {
            $this->insert('finance_settles', [
                ['settle_no' => 'STL-DM-001', 'period_start' => now()->subMonth()->startOfMonth()->format('Y-m-d'), 'period_end' => now()->subMonth()->endOfMonth()->format('Y-m-d'), 'total_amount' => 96500, 'status' => 'closed', 'created_at' => $ts, 'updated_at' => $ts],
                ['settle_no' => 'STL-DM-002', 'period_start' => now()->startOfMonth()->format('Y-m-d'), 'period_end' => now()->format('Y-m-d'), 'total_amount' => 42800, 'status' => 'open', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('finance_notes') === 0) {
            $this->insert('finance_notes', [
                ['note_no' => 'NT-DM-001', 'type' => 'receivable', 'amount' => 2500, 'drawer' => '演示精密电子（深圳）有限公司', 'due_date' => $this->day(30), 'status' => 'outstanding', 'created_at' => $ts, 'updated_at' => $ts],
                ['note_no' => 'NT-DM-002', 'type' => 'payable', 'amount' => 1800, 'drawer' => '演示元器件（中国）有限公司', 'due_date' => $this->day(15), 'status' => 'outstanding', 'created_at' => $ts, 'updated_at' => $ts],
                ['note_no' => 'NT-DM-003', 'type' => 'receivable', 'amount' => 1000, 'drawer' => '演示商贸（广州）有限公司', 'due_date' => $this->day(-5), 'status' => 'settled', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('expense_allocations') === 0) {
            $this->insert('expense_allocations', [
                ['alloc_no' => 'EA-DM-001', 'dept_code' => 'D-PROD', 'amount' => 12000, 'basis' => '工时占比', 'period' => now()->format('Y-m'), 'created_at' => $ts, 'updated_at' => $ts],
                ['alloc_no' => 'EA-DM-002', 'dept_code' => 'D-PUR', 'amount' => 4000, 'basis' => '采购额占比', 'period' => now()->format('Y-m'), 'created_at' => $ts, 'updated_at' => $ts],
                ['alloc_no' => 'EA-DM-003', 'dept_code' => 'D-SAL', 'amount' => 6000, 'basis' => '收入占比', 'period' => now()->format('Y-m'), 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }
    }

    // ---------------------------------------------------------------- CRM

    private function seedCrm(): void
    {
        $ts = $this->now();

        if ($this->count('crm_leads') === 0) {
            $rows = [];
            $leads = [
                ['LD-DM-001', '演示智能家居（华东）有限公司', '刘经理', '13700000001', '官网询盘', 'qualified'],
                ['LD-DM-002', '演示消费电子（华南）有限公司', '陈工', '13700000002', '展会', 'following'],
                ['LD-DM-003', '演示物联网方案商', '王总', '13700000003', '老客户介绍', 'new'],
                ['LD-DM-004', '演示教育硬件公司', '李经理', '13700000004', '线上广告', 'converted'],
                ['LD-DM-005', '演示车载电子有限公司', '赵工', '13700000005', '行业群', 'lost'],
            ];
            foreach ($leads as [$no, $company, $contact, $phone, $source, $status]) {
                $rows[] = [
                    'lead_no' => $no,
                    'company_name' => $company,
                    'contact_name' => $contact,
                    'phone' => $phone,
                    'email' => null,
                    'source' => $source,
                    'status' => $status,
                    'owner_id' => 5,
                    'remark' => '演示线索',
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('crm_leads', $rows);
        }

        if ($this->count('crm_opportunities') === 0) {
            $this->insert('crm_opportunities', [
                ['op_no' => 'OP-DM-001', 'name' => '智能时钟年度框架', 'lead_id' => 1, 'customer_id' => 1, 'expected_amount' => 120000, 'probability' => 70, 'expected_close_date' => $this->day(30), 'stage' => 'negotiating', 'owner_id' => 5, 'created_at' => $ts, 'updated_at' => $ts],
                ['op_no' => 'OP-DM-002', 'name' => 'PCBA 代工项目', 'lead_id' => 2, 'customer_id' => 2, 'expected_amount' => 86000, 'probability' => 50, 'expected_close_date' => $this->day(45), 'stage' => 'proposal', 'owner_id' => 5, 'created_at' => $ts, 'updated_at' => $ts],
                ['op_no' => 'OP-DM-003', 'name' => 'IoT 网关试产', 'lead_id' => 3, 'customer_id' => null, 'expected_amount' => 42000, 'probability' => 30, 'expected_close_date' => $this->day(60), 'stage' => 'qualifying', 'owner_id' => 6, 'created_at' => $ts, 'updated_at' => $ts],
                ['op_no' => 'OP-DM-004', 'name' => '教育硬件整机', 'lead_id' => 4, 'customer_id' => 3, 'expected_amount' => 200000, 'probability' => 90, 'expected_close_date' => $this->day(15), 'stage' => 'closing', 'owner_id' => 5, 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('crm_follow_ups') === 0) {
            $rows = [];
            $items = [
                [1, null, 'phone', '电话沟通年度框架价格，客户要求下调 5%', -10],
                [2, null, 'visit', '上门拜访，确认技术方案可行性', -6],
                [1, 1, 'email', '发送报价单 SQ-DM-001', -4],
                [4, 4, 'phone', '确认首批订单数量与交期', -2],
                [3, 3, 'wechat', '发送产品资料与样品清单', -1],
            ];
            foreach ($items as [$lead, $op, $type, $content, $date]) {
                $rows[] = [
                    'lead_id' => $lead,
                    'opportunity_id' => $op,
                    'user_id' => 5,
                    'type' => $type,
                    'content' => $content,
                    'followed_at' => $this->ts($date, '14:00:00'),
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('crm_follow_ups', $rows);
        }
    }

    // ---------------------------------------------------------------- 人事

    private function seedHr(): void
    {
        $ts = $this->now();

        if ($this->count('hr_departments') === 0) {
            $this->insert('hr_departments', [
                ['code' => 'HR-D01', 'name' => '生产部', 'parent_id' => null, 'manager' => '演示生产经理', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'HR-D02', 'name' => '品质部', 'parent_id' => null, 'manager' => '演示品质经理', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'HR-D03', 'name' => '采购部', 'parent_id' => null, 'manager' => '演示采购经理', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'HR-D04', 'name' => '销售部', 'parent_id' => null, 'manager' => '演示销售经理', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'HR-D05', 'name' => '财务部', 'parent_id' => null, 'manager' => '演示财务经理', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'HR-D06', 'name' => '人事行政部', 'parent_id' => null, 'manager' => '演示人事经理', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('hr_employees') === 0) {
            $names = ['演示员工01', '演示员工02', '演示员工03', '演示员工04', '演示员工05', '演示员工06',
                '演示员工07', '演示员工08', '演示员工09', '演示员工10', '演示员工11', '演示员工12'];
            $rows = [];
            foreach ($names as $i => $name) {
                $rows[] = [
                    'emp_no' => sprintf('EMP%04d', $i + 1),
                    'name' => $name,
                    'gender' => $i % 2 === 0 ? '男' : '女',
                    'birth_date' => sprintf('199%d-0%d-1%d', $i % 10, ($i % 9) + 1, ($i % 9) + 1),
                    'phone' => '1360000' . sprintf('%04d', $i + 1),
                    'department_id' => ($i % 6) + 1,
                    'position' => $i % 3 === 0 ? '操作工' : ($i % 3 === 1 ? '技术员' : '文员'),
                    'hire_date' => $this->day(-300 + $i * 10),
                    'status' => 'active',
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('hr_employees', $rows);

            $att = [];
            foreach (range(1, 12) as $eid) {
                foreach ([-3, -2, -1] as $d) {
                    $att[] = [
                        'employee_id' => $eid,
                        'work_date' => $this->day($d),
                        'check_in' => '08:5' . ($eid % 6) . ':00',
                        'check_out' => '17:5' . ($eid % 6) . ':00',
                        'status' => $d === -2 && $eid % 4 === 0 ? 'late' : 'normal',
                        'created_at' => $ts,
                        'updated_at' => $ts,
                    ];
                }
            }
            $this->insert('hr_attendances', $att);

            $leaves = [
                ['LV-DM-001', 1, 'annual', -5, -4, 2, '年假', 'approved'],
                ['LV-DM-002', 2, 'sick', -2, -2, 1, '门诊病假', 'approved'],
                ['LV-DM-003', 3, 'personal', 2, 3, 2, '事假', 'pending'],
                ['LV-DM-004', 4, 'annual', 5, 9, 5, '年假出行', 'pending'],
            ];
            $rows = [];
            foreach ($leaves as [$no, $eid, $type, $from, $to, $days, $reason, $status]) {
                $rows[] = [
                    'leave_no' => $no,
                    'employee_id' => $eid,
                    'type' => $type,
                    'from_date' => $this->day($from),
                    'to_date' => $this->day($to),
                    'days' => $days,
                    'reason' => $reason,
                    'status' => $status,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('hr_leaves', $rows);

            $sal = [];
            foreach ([1, 2, 3, 4, 5, 6] as $eid) {
                $base = 6000 + $eid * 500;
                $sal[] = [
                    'employee_id' => $eid,
                    'period' => now()->format('Y-m'),
                    'base' => $base,
                    'bonus' => 500,
                    'deduction' => 300,
                    'net' => $base + 500 - 300,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('hr_salaries', $sal);

            $perf = [];
            foreach ([1, 2, 3, 4, 5, 6] as $eid) {
                $score = 70 + ($eid % 5) * 5;
                $perf[] = [
                    'employee_id' => $eid,
                    'period' => now()->subMonth()->format('Y-m'),
                    'score' => $score,
                    'grade' => $score >= 90 ? 'A' : ($score >= 80 ? 'B' : 'C'),
                    'comment' => '演示绩效评价',
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('hr_performances', $perf);

            $this->insert('hr_recruitments', [
                ['req_no' => 'RC-DM-100', 'position' => 'SMT 操作工', 'headcount' => 5, 'department' => '生产部', 'requirement' => '1 年以上贴片经验', 'status' => 'open', 'created_at' => $ts, 'updated_at' => $ts],
                ['req_no' => 'RC-DM-101', 'position' => '品质工程师', 'headcount' => 2, 'department' => '品质部', 'requirement' => '熟悉 IPC-A-610', 'status' => 'open', 'created_at' => $ts, 'updated_at' => $ts],
                ['req_no' => 'RC-DM-102', 'position' => '采购专员', 'headcount' => 1, 'department' => '采购部', 'requirement' => '电子料采购经验', 'status' => 'closed', 'created_at' => $ts, 'updated_at' => $ts],
            ]);

            $this->insert('assets', [
                ['asset_code' => 'AS-DM-001', 'name' => '笔记本电脑', 'employee_id' => 1, 'status' => 'in_use', 'created_at' => $ts, 'updated_at' => $ts],
                ['asset_code' => 'AS-DM-002', 'name' => '测试治具', 'employee_id' => 2, 'status' => 'in_use', 'created_at' => $ts, 'updated_at' => $ts],
                ['asset_code' => 'AS-DM-003', 'name' => '扫码枪', 'employee_id' => 3, 'status' => 'in_use', 'created_at' => $ts, 'updated_at' => $ts],
            ]);

            $this->insert('time_entries', [
                ['employee_id' => 1, 'project_id' => null, 'work_date' => $this->day(-1), 'hours' => 8, 'description' => '产线排产', 'created_at' => $ts, 'updated_at' => $ts],
                ['employee_id' => 2, 'project_id' => null, 'work_date' => $this->day(-1), 'hours' => 7.5, 'description' => '品质巡检', 'created_at' => $ts, 'updated_at' => $ts],
                ['employee_id' => 3, 'project_id' => null, 'work_date' => $this->day(-2), 'hours' => 8, 'description' => '工单报工', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }
    }

    // ---------------------------------------------------------------- 商品与营销

    private function seedProductMarketing(): void
    {
        $ts = $this->now();

        if ($this->count('product_categories') === 0) {
            $this->insert('product_categories', [
                ['code' => 'PC-01', 'name' => '智能家居', 'parent_id' => null, 'sort' => 1, 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'PC-02', 'name' => '数码配件', 'parent_id' => null, 'sort' => 2, 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'PC-03', 'name' => '智能时钟', 'parent_id' => 1, 'sort' => 1, 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'PC-04', 'name' => '充电器', 'parent_id' => 2, 'sort' => 2, 'created_at' => $ts, 'updated_at' => $ts],
            ]);
            $this->insert('product_brands', [
                ['code' => 'BR-01', 'name' => '演示自有品牌', 'logo' => null, 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'BR-02', 'name' => '演示合作品牌', 'logo' => null, 'created_at' => $ts, 'updated_at' => $ts],
            ]);

            $this->insert('product_spus', [
                ['spu_no' => 'SPU-DM-001', 'name' => '智能时钟 Pro', 'category_id' => 3, 'brand_id' => 1, 'unit' => '台', 'market_price' => 129, 'sale_price' => 99, 'status' => 'on_sale', 'description' => '带温湿度显示的桌面智能时钟', 'created_at' => $ts, 'updated_at' => $ts],
                ['spu_no' => 'SPU-DM-002', 'name' => '智能时钟 Mini', 'category_id' => 3, 'brand_id' => 1, 'unit' => '台', 'market_price' => 89, 'sale_price' => 69, 'status' => 'on_sale', 'description' => '桌面迷你时钟', 'created_at' => $ts, 'updated_at' => $ts],
                ['spu_no' => 'SPU-DM-003', 'name' => '65W 氮化镓充电器', 'category_id' => 4, 'brand_id' => 2, 'unit' => '个', 'market_price' => 159, 'sale_price' => 129, 'status' => 'on_sale', 'description' => '双口快充', 'created_at' => $ts, 'updated_at' => $ts],
                ['spu_no' => 'SPU-DM-004', 'name' => 'USB4 数据线', 'category_id' => 4, 'brand_id' => 2, 'unit' => '条', 'market_price' => 59, 'sale_price' => 39, 'status' => 'off_sale', 'description' => '1m 编织线', 'created_at' => $ts, 'updated_at' => $ts],
            ]);

            $this->insert('product_skus', [
                ['spu_id' => 1, 'sku_code' => 'SKU-DM-001-BK', 'spec' => '黑色', 'price' => 99, 'stock' => 120, 'created_at' => $ts, 'updated_at' => $ts],
                ['spu_id' => 1, 'sku_code' => 'SKU-DM-001-WH', 'spec' => '白色', 'price' => 99, 'stock' => 80, 'created_at' => $ts, 'updated_at' => $ts],
                ['spu_id' => 2, 'sku_code' => 'SKU-DM-002-BK', 'spec' => '黑色', 'price' => 69, 'stock' => 200, 'created_at' => $ts, 'updated_at' => $ts],
                ['spu_id' => 3, 'sku_code' => 'SKU-DM-003-EU', 'spec' => '欧规', 'price' => 129, 'stock' => 60, 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('promotions') === 0) {
            $this->insert('promotions', [
                ['code' => 'PM-DM-001', 'name' => '双十二满减', 'type' => 'discount', 'value' => 0.9, 'start_date' => $this->day(10), 'end_date' => $this->day(15), 'status' => 'active', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'PM-DM-002', 'name' => '新品首发直降', 'type' => 'price', 'value' => 20, 'start_date' => $this->day(-5), 'end_date' => $this->day(5), 'status' => 'active', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'PM-DM-003', 'name' => '会员专享 95 折', 'type' => 'discount', 'value' => 0.95, 'start_date' => $this->day(-30), 'end_date' => $this->day(60), 'status' => 'active', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'PM-DM-004', 'name' => '双十一活动', 'type' => 'discount', 'value' => 0.85, 'start_date' => $this->day(-30), 'end_date' => $this->day(-20), 'status' => 'ended', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('coupons') === 0) {
            $this->insert('coupons', [
                ['coupon_no' => 'CP-DM-001', 'name' => '满 100 减 10', 'amount' => 10, 'min_spend' => 100, 'total_qty' => 1000, 'received_qty' => 320, 'expire_date' => $this->day(30), 'status' => 'active', 'created_at' => $ts, 'updated_at' => $ts],
                ['coupon_no' => 'CP-DM-002', 'name' => '满 200 减 30', 'amount' => 30, 'min_spend' => 200, 'total_qty' => 500, 'received_qty' => 180, 'expire_date' => $this->day(20), 'status' => 'active', 'created_at' => $ts, 'updated_at' => $ts],
                ['coupon_no' => 'CP-DM-003', 'name' => '新客立减 5', 'amount' => 5, 'min_spend' => 0, 'total_qty' => 2000, 'received_qty' => 1500, 'expire_date' => $this->day(-1), 'status' => 'expired', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('aftersales_tickets') === 0) {
            $this->insert('aftersales_tickets', [
                ['ticket_no' => 'TK-DM-001', 'ecom_order_no' => 'EC-DM-001', 'customer_name' => '演示买家甲', 'customer_phone' => '13500000001', 'type' => 'return', 'description' => '收到货后显示屏不亮', 'status' => 'processing', 'handler_id' => 5, 'created_at' => $ts, 'updated_at' => $ts],
                ['ticket_no' => 'TK-DM-002', 'ecom_order_no' => 'EC-DM-002', 'customer_name' => '演示买家乙', 'customer_phone' => '13500000002', 'type' => 'exchange', 'description' => '外壳有划痕', 'status' => 'closed', 'handler_id' => 5, 'created_at' => $ts, 'updated_at' => $ts],
                ['ticket_no' => 'TK-DM-003', 'ecom_order_no' => 'EC-DM-003', 'customer_name' => '演示买家丙', 'customer_phone' => '13500000003', 'type' => 'refund', 'description' => '七天无理由退货', 'status' => 'pending', 'handler_id' => null, 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('product_reviews') === 0) {
            $rows = [];
            $items = [[1, '演示买家甲', 5, '走时准确，外观漂亮'], [1, '演示买家乙', 4, '功能不错，包装稍简'], [2, '演示买家丙', 5, '性价比高'], [3, '演示买家丁', 3, '充电发热明显'], [1, '演示买家戊', 5, '第二次回购']];
            foreach ($items as [$spu, $nick, $rating, $content]) {
                $rows[] = [
                    'spu_id' => $spu,
                    'buyer_nick' => $nick,
                    'rating' => $rating,
                    'content' => $content,
                    'is_hidden' => 0,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('product_reviews', $rows);
        }
    }

    // ---------------------------------------------------------------- 平台与事件

    private function seedPlatformEvents(): void
    {
        $ts = $this->now();

        if ($this->count('platform_api_logs') === 0) {
            $rows = [];
            $items = [
                ['taobao', '/api/order/list', 'GET', 200, 320],
                ['taobao', '/api/item/publish', 'POST', 200, 540],
                ['jd', '/api/order/list', 'GET', 200, 280],
                ['jd', '/api/stock/sync', 'POST', 500, 1200],
                ['douyin', '/api/order/list', 'GET', 200, 410],
                ['douyin', '/api/refund/apply', 'POST', 400, 260],
            ];
            foreach ($items as $i => [$platform, $path, $method, $code, $ms]) {
                $rows[] = [
                    'platform' => $platform,
                    'api_path' => $path,
                    'method' => $method,
                    'http_code' => $code,
                    'duration_ms' => $ms,
                    'request_body' => '{"demo":true}',
                    'response_body' => $code === 200 ? '{"success":true}' : '{"error":"demo"}',
                    'called_at' => $this->ts(-3 + $i, '11:00:00'),
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('platform_api_logs', $rows);
        }

        if ($this->count('platform_status_mappings') === 0) {
            $this->insert('platform_status_mappings', [
                ['platform' => 'taobao', 'platform_status' => 'WAIT_BUYER_PAY', 'local_status' => 'pending', 'created_at' => $ts, 'updated_at' => $ts],
                ['platform' => 'taobao', 'platform_status' => 'WAIT_SELLER_SEND_GOODS', 'local_status' => 'paid', 'created_at' => $ts, 'updated_at' => $ts],
                ['platform' => 'jd', 'platform_status' => 'WAIT_SELLER_STOCK_OUT', 'local_status' => 'paid', 'created_at' => $ts, 'updated_at' => $ts],
                ['platform' => 'jd', 'platform_status' => 'FINISHED', 'local_status' => 'completed', 'created_at' => $ts, 'updated_at' => $ts],
                ['platform' => 'douyin', 'platform_status' => 'PAYED', 'local_status' => 'paid', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('domain_events') === 0) {
            $rows = [];
            $items = [
                ['SalesOrderConfirmed', 'sales_order', 3, -8],
                ['ProductionOrderReleased', 'production_order', 3, -6],
                ['GoodsReceived', 'goods_receipt', 2, -5],
                ['InventoryAdjusted', 'inventory', 1, -4],
                ['InvoiceIssued', 'customer_invoice', 3, -3],
                ['PaymentReceived', 'payment', 2, -2],
                ['ReturnApproved', 'customer_return', 1, -1],
            ];
            foreach ($items as [$name, $agg, $aggId, $d]) {
                $rows[] = [
                    'name' => $name,
                    'agg_type' => $agg,
                    'agg_id' => $aggId,
                    'data' => json_encode(['demo' => true, 'event' => $name], JSON_UNESCAPED_UNICODE),
                    'created_at' => $this->ts($d, '10:20:00'),
                    'updated_at' => $ts,
                ];
            }
            $this->insert('domain_events', $rows);
        }

        if ($this->count('integration_inbox') === 0) {
            $this->insert('integration_inbox', [
                ['event_type' => 'order.created', 'source' => 'taobao', 'payload' => '{"order_no":"EC-DM-001"}', 'status' => 'processed', 'processed_at' => $this->ts(-2), 'created_at' => $ts, 'updated_at' => $ts],
                ['event_type' => 'order.paid', 'source' => 'jd', 'payload' => '{"order_no":"EC-DM-002"}', 'status' => 'processed', 'processed_at' => $this->ts(-1), 'created_at' => $ts, 'updated_at' => $ts],
                ['event_type' => 'refund.apply', 'source' => 'douyin', 'payload' => '{"refund_no":"RF-DM-001"}', 'status' => 'pending', 'processed_at' => null, 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('sync_tasks') === 0) {
            $this->insert('sync_tasks', [
                ['name' => '淘宝订单同步', 'target' => 'taobao', 'direction' => 'in', 'data_type' => 'order', 'cron_expr' => '*/10 * * * *', 'status' => 'running', 'last_run_at' => $this->ts(0, '11:10:00'), 'created_at' => $ts, 'updated_at' => $ts],
                ['name' => '京东库存推送', 'target' => 'jd', 'direction' => 'out', 'data_type' => 'stock', 'cron_expr' => '0 * * * *', 'status' => 'running', 'last_run_at' => $this->ts(0, '11:00:00'), 'created_at' => $ts, 'updated_at' => $ts],
                ['name' => '抖音商品同步', 'target' => 'douyin', 'direction' => 'out', 'data_type' => 'product', 'cron_expr' => '0 2 * * *', 'status' => 'paused', 'last_run_at' => $this->ts(-1, '02:00:00'), 'created_at' => $ts, 'updated_at' => $ts],
            ]);

            $rows = [];
            for ($i = 0; $i < 6; $i++) {
                $rows[] = [
                    'sync_task_id' => ($i % 3) + 1,
                    'run_at' => $this->ts(-3 + intdiv($i, 3), sprintf('%02d:00:00', 9 + $i)),
                    'status' => $i % 4 === 3 ? 'failed' : 'success',
                    'total_count' => 100 + $i * 10,
                    'success_count' => $i % 4 === 3 ? 80 : 100 + $i * 10,
                    'message' => $i % 4 === 3 ? '部分订单状态映射失败' : '同步完成',
                    'created_at' => $ts,
                    'updated_at' => $ts,
                    'replay_of' => null,
                    'replayed_at' => null,
                ];
            }
            $this->insert('sync_logs', $rows);
        }
    }

    // ---------------------------------------------------------------- 协同与系统

    private function seedCollab(): void
    {
        $ts = $this->now();

        if ($this->count('announcements') === 0) {
            $items = [
                ['关于 12 月排产计划的通知', 'production', -10],
                ['质量月活动启动', 'quality', -8],
                ['系统维护公告（本周日凌晨）', 'system', -5],
                ['年度盘点安排', 'inventory', -2],
                ['新员工入职培训通知', 'hr', 0],
            ];
            $rows = [];
            foreach ($items as [$title, $cat, $d]) {
                $rows[] = [
                    'title' => $title,
                    'content' => '演示公告内容：' . $title . '。本公告为演示数据，不对应任何真实企业。',
                    'category' => $cat,
                    'publisher_id' => 1,
                    'published_at' => $this->ts($d, '08:30:00'),
                    'status' => 'published',
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('announcements', $rows);
        }

        if ($this->count('knowledge_categories') === 0) {
            $this->insert('knowledge_categories', [
                ['code' => 'KC-01', 'name' => '工艺流程', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'KC-02', 'name' => '质量标准', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'KC-03', 'name' => '设备维护', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'KC-04', 'name' => '制度流程', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('knowledges') === 0) {
            $items = [
                ['SMT 贴片常见缺陷与对策', '1. 连锡：调整钢网开孔与锡膏量；2. 立碑：优化回流曲线；3. 偏移：校准贴装坐标。'],
                ['来料检验抽样规则说明', '按 GB/T 2828.1 一般检验水平 II，AQL 1.0，关键件加严检验。'],
                ['回流焊温度曲线设定指南', '预热区 150-180℃，恒温区 180-200℃，回流峰值 235-245℃，冷却速率 <4℃/s。'],
                ['贴片机日常点检清单', '吸嘴清洁、导轨润滑、真空压力、供料器校准，每班次一次。'],
                ['工单完工入库操作规范', '报工完成后由班组长提交完工，仓库核对数量后入库并更新库存。'],
            ];
            $rows = [];
            foreach ($items as [$title, $content]) {
                $rows[] = [
                    'title' => $title,
                    'content' => $content,
                    'creator_id' => 1,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('knowledges', $rows);
        }

        if ($this->count('files') === 0) {
            $items = [
                ['智能时钟_BOM_V2.xlsx', 'demo/bom-v2.xlsx'],
                ['SMT 工艺规范.pdf', 'demo/smt-spec.pdf'],
                ['来料检验标准.docx', 'demo/iqc-std.docx'],
                ['客户 A 年度框架合同.pdf', 'demo/contract-a.pdf'],
                ['产线布局图.png', 'demo/layout.png'],
            ];
            $rows = [];
            foreach ($items as [$name, $path]) {
                $rows[] = [
                    'name' => $name,
                    'path' => $path,
                    'uploader_id' => 8,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('files', $rows);

            $this->insert('file_versions', [
                ['file_id' => 1, 'version' => 'V1', 'path' => 'demo/bom-v1.xlsx', 'created_at' => $ts, 'updated_at' => $ts],
                ['file_id' => 1, 'version' => 'V2', 'path' => 'demo/bom-v2.xlsx', 'created_at' => $ts, 'updated_at' => $ts],
                ['file_id' => 2, 'version' => 'V1', 'path' => 'demo/smt-spec.pdf', 'created_at' => $ts, 'updated_at' => $ts],
            ]);

            $this->insert('department_files', [
                ['department_id' => 1, 'file_name' => '产线排产表.xlsx', 'file_path' => 'demo/dept/prod-plan.xlsx', 'created_at' => $ts, 'updated_at' => $ts],
                ['department_id' => 2, 'file_name' => '质量月报.pdf', 'file_path' => 'demo/dept/quality.pdf', 'created_at' => $ts, 'updated_at' => $ts],
                ['department_id' => 3, 'file_name' => '供应商名录.xlsx', 'file_path' => 'demo/dept/suppliers.xlsx', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('messages') === 0) {
            $this->insert('messages', [
                ['from_user_id' => 8, 'to_user_id' => 9, 'content' => '工单 WO-DM-002 下午补料 60000 颗电阻', 'is_read' => 1, 'created_at' => $this->ts(-2, '10:00:00'), 'updated_at' => $ts],
                ['from_user_id' => 9, 'to_user_id' => 8, 'content' => '已登记领料单 MI-DM-002', 'is_read' => 1, 'created_at' => $this->ts(-2, '10:30:00'), 'updated_at' => $ts],
                ['from_user_id' => 6, 'to_user_id' => 1, 'content' => 'PO-DM-004 供应商确认下周一到货', 'is_read' => 0, 'created_at' => $this->ts(-1, '09:00:00'), 'updated_at' => $ts],
                ['from_user_id' => 12, 'to_user_id' => 2, 'content' => 'FQC 批次有 3 pcs 功能不良，请评估', 'is_read' => 0, 'created_at' => $this->ts(0, '08:40:00'), 'updated_at' => $ts],
            ]);
        }

        if ($this->count('todos') === 0) {
            $this->insert('todos', [
                ['user_id' => 1, 'title' => '审批采购申请 PR-DM-002', 'description' => '核对预算与价格', 'due_at' => $this->ts(1, '18:00:00'), 'status' => 'pending', 'created_at' => $ts, 'updated_at' => $ts],
                ['user_id' => 1, 'title' => '确认 12 月排产计划', 'description' => '与销售确认订单交期', 'due_at' => $this->ts(2, '18:00:00'), 'status' => 'pending', 'created_at' => $ts, 'updated_at' => $ts],
                ['user_id' => 8, 'title' => '跟踪 WO-DM-003 开工', 'description' => '确认物料齐套', 'due_at' => $this->ts(0, '18:00:00'), 'status' => 'done', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('media_files') === 0) {
            $this->insert('media_files', [
                ['file_name' => '产品主图.png', 'file_path' => 'demo/media/product.png', 'file_size' => 102400, 'mime_type' => 'image/png', 'category' => 'product', 'uploader_id' => 8, 'ref_type' => 'material', 'ref_id' => 10, 'created_at' => $ts],
                ['file_name' => '工艺流程图.png', 'file_path' => 'demo/media/process.png', 'file_size' => 204800, 'mime_type' => 'image/png', 'category' => 'process', 'uploader_id' => 8, 'ref_type' => null, 'ref_id' => null, 'created_at' => $ts],
            ]);
        }

        if ($this->count('system_settings') === 0) {
            $this->insert('system_settings', [
                ['config_key' => 'company.display_name', 'config_value' => '电子制造企业（演示）', 'group_name' => 'basic', 'description' => '系统显示名称', 'is_public' => 1, 'created_at' => $ts, 'updated_at' => $ts],
                ['config_key' => 'inventory.low_stock_alert', 'config_value' => '1', 'group_name' => 'inventory', 'description' => '低库存提醒开关', 'is_public' => 1, 'created_at' => $ts, 'updated_at' => $ts],
                ['config_key' => 'quality.aql', 'config_value' => '1.0', 'group_name' => 'quality', 'description' => '默认 AQL 值', 'is_public' => 0, 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }
    }

    // ---------------------------------------------------------------- 研发项目

    private function seedRnd(): void
    {
        $ts = $this->now();

        if ($this->count('rnd_projects') === 0) {
            $list = [
                ['RP-DM-001', '智能时钟 Pro 二代开发', 'CW-PRO2', 1, -90, 30, 'in_progress', 65, 'design', '智能时钟 Pro 二代'],
                ['RP-DM-002', '65W 氮化镓充电器试产', 'GAN-65W', 2, -60, 10, 'in_progress', 80, 'trial', '65W 氮化镓充电器'],
                ['RP-DM-003', 'IoT 网关预研', 'IOT-GW1', 8, -30, 90, 'planning', 20, 'concept', 'IoT 网关'],
                ['RP-DM-004', '旧款时钟降本项目', 'CW-ECO', 2, -120, -10, 'completed', 100, 'closed', '智能时钟 Mini'],
            ];
            $rows = [];
            foreach ($list as [$code, $name, $model, $creator, $start, $end, $status, $progress, $stage, $product]) {
                $rows[] = [
                    'project_code' => $code,
                    'project_name' => $name,
                    'product_model' => $model,
                    'creator_id' => $creator,
                    'start_date' => $this->day($start),
                    'end_date' => $this->day($end),
                    'status' => $status,
                    'progress' => $progress,
                    'stage' => $stage,
                    'stage_changed_at' => $this->ts(-5),
                    'product_name' => $product,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('rnd_projects', $rows);

            $tasks = [];
            $types = [1, 2, 3, 4, 5, 6];
            $statuses = ['pending', 'in_progress', 'in_progress', 'completed', 'completed', 'pending'];
            for ($i = 0; $i < 12; $i++) {
                $tasks[] = [
                    'project_id' => ($i % 4) + 1,
                    'task_type_id' => $types[$i % 6],
                    'department_id' => ($i % 5) + 1,
                    'executor_id' => 8 + ($i % 4),
                    'auditor_id' => 2,
                    'start_date' => $this->day(-40 + $i * 5),
                    'end_date' => $this->day(-20 + $i * 5),
                    'status' => $statuses[$i % 6],
                    'is_overdue' => $i % 7 === 6 ? 1 : 0,
                    'extension_days' => $i % 7 === 6 ? 5 : 0,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                    'name' => '演示研发任务 ' . ($i + 1),
                ];
            }
            $this->insert('rnd_project_tasks', $tasks);

            $docs = [];
            for ($i = 0; $i < 6; $i++) {
                $docs[] = [
                    'project_id' => ($i % 3) + 1,
                    'task_id' => ($i % 6) + 1,
                    'version_no' => 'V' . (1 + $i % 2),
                    'file_id' => ($i % 5) + 1,
                    'uploader_id' => 8,
                    'audit_status' => $i % 3 === 0 ? 'pending' : 'approved',
                    'auditor_id' => $i % 3 === 0 ? null : 2,
                    'audit_at' => $i % 3 === 0 ? null : $this->ts(-2),
                    'audit_remark' => $i % 3 === 0 ? null : '符合要求',
                    'created_at' => $ts,
                    'is_baseline' => $i % 2,
                    'baseline_at' => $i % 2 ? $this->ts(-3) : null,
                    'superseded_by' => null,
                ];
            }
            $this->insert('rnd_project_documents', $docs);

            $this->insert('rnd_extension_applications', [
                ['task_id' => 7, 'applicant_id' => 8, 'apply_days' => 5, 'reason' => '供应商样品延期', 'status' => 'pending', 'approver_id' => null, 'approved_at' => null],
                ['task_id' => 2, 'applicant_id' => 9, 'apply_days' => 3, 'reason' => '设备调试占用工时', 'status' => 'approved', 'approver_id' => 2, 'approved_at' => $this->ts(-6)],
            ]);

            $this->insert('rnd_collab_documents', [
                ['project_id' => 1, 'title' => '二代产品需求规格说明书', 'content' => '演示文档：需求规格说明，包含外观、功能、成本目标。', 'version' => 1, 'creator_id' => 8, 'updater_id' => 8, 'doc_type' => 'doc', 'extension' => 'md', 'storage_path' => 'demo/rnd/req.md', 'doc_key' => 'rnd-req-1', 'created_at' => $ts, 'updated_at' => $ts],
                ['project_id' => 2, 'title' => '充电器试产问题清单', 'content' => '演示文档：试产问题与对策。', 'version' => 1, 'creator_id' => 9, 'updater_id' => 9, 'doc_type' => 'doc', 'extension' => 'md', 'storage_path' => 'demo/rnd/trial.md', 'doc_key' => 'rnd-trial-1', 'created_at' => $ts, 'updated_at' => $ts],
            ]);

            $this->insert('rnd_project_logs', [
                ['project_id' => 1, 'task_id' => 1, 'user_id' => 8, 'action' => 'create', 'detail' => '创建项目并拆解任务', 'log_type' => 'system', 'created_at' => $this->ts(-90), 'updated_at' => $ts],
                ['project_id' => 1, 'task_id' => 2, 'user_id' => 2, 'action' => 'approve', 'detail' => '审核通过结构设计任务', 'log_type' => 'audit', 'created_at' => $this->ts(-60), 'updated_at' => $ts],
                ['project_id' => 2, 'task_id' => 5, 'user_id' => 9, 'action' => 'update', 'detail' => '更新试产问题清单', 'log_type' => 'edit', 'created_at' => $this->ts(-10), 'updated_at' => $ts],
            ]);
        }
    }

    // ---------------------------------------------------------------- 计划 (MRP/APS/采购计划)

    private function seedPlanning(): void
    {
        $ts = $this->now();

        if ($this->count('procurement_plans') === 0) {
            $this->insert('procurement_plans', [
                ['plan_no' => 'PP-DM-001', 'title' => '12 月电子料采购计划', 'plan_date' => $this->day(3), 'total_amount' => 58600, 'status' => 'approved', 'created_at' => $ts, 'updated_at' => $ts],
                ['plan_no' => 'PP-DM-002', 'title' => '包装耗材季度计划', 'plan_date' => $this->day(5), 'total_amount' => 12000, 'status' => 'draft', 'created_at' => $ts, 'updated_at' => $ts],
                ['plan_no' => 'PP-DM-003', 'title' => '结构件补充计划', 'plan_date' => $this->day(-5), 'total_amount' => 8600, 'status' => 'completed', 'created_at' => $ts, 'updated_at' => $ts],
            ]);

            $this->insert('procurement_arrivals', [
                ['arrival_no' => 'PA-DM-001', 'plan_id' => 1, 'supplier_id' => 1, 'arrival_date' => $this->day(-2), 'status' => 'arrived', 'created_at' => $ts, 'updated_at' => $ts],
                ['arrival_no' => 'PA-DM-002', 'plan_id' => 1, 'supplier_id' => 3, 'arrival_date' => $this->day(3), 'status' => 'scheduled', 'created_at' => $ts, 'updated_at' => $ts],
                ['arrival_no' => 'PA-DM-003', 'plan_id' => 3, 'supplier_id' => 2, 'arrival_date' => $this->day(-3), 'status' => 'arrived', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('mrp_suggestions') === 0) {
            $rows = [];
            $items = [[1, 1, 3, 20000, 8000, 0, 12000, 'stock'], [3, 3, 5, 2000, 600, 0, 1400, 'so'], [8, 1, 8, 500, 120, 200, 180, 'wo']];
            foreach ($items as [$m, $sup, $days, $gross, $avail, $transit, $net, $src]) {
                $rows[] = [
                    'material_id' => $m,
                    'supplier_id' => $sup,
                    'required_date' => $this->day($days),
                    'gross_qty' => $gross,
                    'available_qty' => $avail,
                    'transit_qty' => $transit,
                    'net_qty' => $net,
                    'status' => 'suggested',
                    'demand_source' => $src,
                    'requisition_id' => null,
                    'created_by' => 4,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('mrp_suggestions', $rows);
        }

        if ($this->count('aps_plans') === 0) {
            $pid = null;
            $this->insert('aps_plans', [[
                'plan_no' => 'APS-DM-001',
                'horizon_start' => $this->day(1),
                'horizon_end' => $this->day(14),
                'status' => 'draft',
                'summary_json' => json_encode(['orders' => 4, 'load' => 0.78], JSON_UNESCAPED_UNICODE),
                'created_by' => 8,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);
            $pid = (int) DB::connection($this->conn)->table('aps_plans')->where('plan_no', 'APS-DM-001')->value('id');

            $rows = [];
            $ops = [['SMT 贴片', 1], ['回流焊', 1], ['装配', 2], ['测试', 3]];
            foreach ($ops as $i => [$op, $wc]) {
                $rows[] = [
                    'plan_id' => $pid,
                    'production_order_id' => 3,
                    'operation_seq' => $i + 1,
                    'operation_name' => $op,
                    'work_center_id' => $wc,
                    'work_date' => $this->day(1 + $i),
                    'hours' => 8,
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
            $this->insert('aps_plan_entries', $rows);
        }
    }

    // ---------------------------------------------------------------- AI

    private function seedAi(): void
    {
        $ts = $this->now();

        if ($this->count('ai_providers') === 0) {
            // api_key 为 encrypted cast，直插必须自行加密
            $this->insert('ai_providers', [
                ['name' => '演示大模型服务 A', 'provider_code' => 'demo-a', 'api_base' => 'https://api.example.com/v1', 'api_key' => \Illuminate\Support\Facades\Crypt::encryptString('demo-key-a'), 'model' => 'demo-model-1', 'status' => 'inactive', 'created_at' => $ts, 'updated_at' => $ts],
                ['name' => '演示大模型服务 B', 'provider_code' => 'demo-b', 'api_base' => 'https://api.example.com/v1', 'api_key' => \Illuminate\Support\Facades\Crypt::encryptString('demo-key-b'), 'model' => 'demo-model-2', 'status' => 'inactive', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('ai_conversations') === 0) {
            $cid = null;
            $this->insert('ai_conversations', [[
                'title' => '演示会话：BOM 缺料分析',
                'user_id' => 1,
                'provider_id' => 1,
                'conversation_type' => 'chat',
                'skill_code' => null,
                'message_count' => 2,
                'last_message_at' => $this->ts(-1, '16:00:00'),
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);
            $cid = (int) DB::connection($this->conn)->table('ai_conversations')->max('id');

            $this->insert('ai_messages', [
                ['conversation_id' => $cid, 'role' => 'user', 'content' => '帮我分析 WO-DM-002 的缺料情况', 'token_usage' => 30, 'meta' => null, 'created_at' => $this->ts(-1, '15:58:00'), 'updated_at' => $ts],
                ['conversation_id' => $cid, 'role' => 'assistant', 'content' => '演示回复：WO-DM-002 缺 MCU 1400 pcs，建议采购 1400 pcs，预计 5 天到货。', 'token_usage' => 80, 'meta' => null, 'created_at' => $this->ts(-1, '16:00:00'), 'updated_at' => $ts],
            ]);
        }

        if ($this->count('ai_models') === 0) {
            $this->insert('ai_models', [
                ['provider' => 'demo-a', 'model_name' => 'demo-model-1', 'is_default' => 1, 'created_at' => $ts, 'updated_at' => $ts],
                ['provider' => 'demo-b', 'model_name' => 'demo-model-2', 'is_default' => 0, 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('ai_settings') === 0) {
            $this->insert('ai_settings', [
                ['key' => 'ai.enabled', 'value' => '0', 'created_at' => $ts, 'updated_at' => $ts],
                ['key' => 'ai.default_model', 'value' => 'demo-model-1', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('ai_doc_templates') === 0) {
            $this->insert('ai_doc_templates', [
                ['code' => 'AI-SOP', 'name' => 'SOP 文档模板', 'prompt' => '请根据工序信息生成标准作业指导书', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'AI-QR', 'name' => '质量报告模板', 'prompt' => '请根据检验记录生成质量分析报告', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }
    }

    // ---------------------------------------------------------------- 多租户 / 企微 / 语音

    private function seedTenantWecomVoice(): void
    {
        $ts = $this->now();

        if ($this->count('tenants') === 0) {
            $this->insert('tenants', [
                ['code' => 'T-DEMO-1', 'name' => '演示租户一（电子制造）', 'domain' => 'demo1.example.com', 'contact_name' => '演示管理员', 'contact_phone' => '13000000001', 'expired_at' => $this->day(365), 'status' => 'active', 'settings' => '{}', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'T-DEMO-2', 'name' => '演示租户二（精密加工）', 'domain' => 'demo2.example.com', 'contact_name' => '演示管理员', 'contact_phone' => '13000000002', 'expired_at' => $this->day(180), 'status' => 'active', 'settings' => '{}', 'created_at' => $ts, 'updated_at' => $ts],
                ['code' => 'T-DEMO-3', 'name' => '演示租户三（已停用）', 'domain' => 'demo3.example.com', 'contact_name' => '演示管理员', 'contact_phone' => '13000000003', 'expired_at' => $this->day(-30), 'status' => 'disabled', 'settings' => '{}', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('wecom_configs') === 0) {
            // app_secret / token / encoding_aes_key 在模型上是 encrypted cast，
            // 直接写库必须自行加密，否则页面读取解密会抛 "payload is invalid"。
            $this->insert('wecom_configs', [[
                'corp_id' => 'ww-demo-corp',
                'agent_id' => '1000001',
                'app_secret' => \Illuminate\Support\Facades\Crypt::encryptString('demo-secret'),
                'token' => \Illuminate\Support\Facades\Crypt::encryptString('demo-token'),
                'encoding_aes_key' => \Illuminate\Support\Facades\Crypt::encryptString(str_repeat('A', 43)),
                'status' => 'inactive',
                'created_at' => $ts,
                'updated_at' => $ts,
            ]]);

            $this->insert('wecom_message_logs', [
                ['msg_id' => 'WM-DM-001', 'to_user' => 'demo-user-1', 'msg_type' => 'text', 'content' => '工单 WO-DM-003 已下达', 'status' => 'sent', 'error_msg' => null, 'sent_at' => $this->ts(-1), 'created_at' => $ts, 'updated_at' => $ts],
                ['msg_id' => 'WM-DM-002', 'to_user' => 'demo-user-2', 'msg_type' => 'text', 'content' => '审批提醒：PR-DM-002', 'status' => 'sent', 'error_msg' => null, 'sent_at' => $this->ts(0), 'created_at' => $ts, 'updated_at' => $ts],
                ['msg_id' => 'WM-DM-003', 'to_user' => 'demo-user-3', 'msg_type' => 'text', 'content' => '质量异常提醒', 'status' => 'failed', 'error_msg' => '演示：用户未关注企业', 'sent_at' => $this->ts(0), 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }

        if ($this->count('voice_rooms') === 0) {
            $this->insert('voice_rooms', [
                ['room_no' => 'VR-DM-001', 'name' => '生产例会', 'host_id' => 8, 'status' => 'ended', 'started_at' => $this->ts(-3, '08:30:00'), 'ended_at' => $this->ts(-3, '09:10:00'), 'created_at' => $ts, 'updated_at' => $ts],
                ['room_no' => 'VR-DM-002', 'name' => '质量分析会', 'host_id' => 12, 'status' => 'active', 'started_at' => $this->ts(0, '10:00:00'), 'ended_at' => null, 'created_at' => $ts, 'updated_at' => $ts],
            ]);

            $this->insert('voice_participants', [
                ['voice_room_id' => 1, 'user_id' => 8, 'role' => 'host', 'joined_at' => $this->ts(-3, '08:30:00'), 'left_at' => $this->ts(-3, '09:10:00'), 'created_at' => $ts, 'updated_at' => $ts],
                ['voice_room_id' => 1, 'user_id' => 9, 'role' => 'guest', 'joined_at' => $this->ts(-3, '08:32:00'), 'left_at' => $this->ts(-3, '09:10:00'), 'created_at' => $ts, 'updated_at' => $ts],
                ['voice_room_id' => 2, 'user_id' => 12, 'role' => 'host', 'joined_at' => $this->ts(0, '10:00:00'), 'left_at' => null, 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }
    }

    // ---------------------------------------------------------------- 电商店铺

    private function seedEcomShops(): void
    {
        $ts = $this->now();

        if ($this->count('shops') === 0) {
            $this->insert('shops', [
                ['platform' => 'taobao', 'shop_name' => '演示旗舰店（淘宝）', 'shop_url' => 'https://demo.example.com/taobao', 'status' => 'active', 'created_at' => $ts, 'updated_at' => $ts],
                ['platform' => 'jd', 'shop_name' => '演示自营店（京东）', 'shop_url' => 'https://demo.example.com/jd', 'status' => 'active', 'created_at' => $ts, 'updated_at' => $ts],
                ['platform' => 'douyin', 'shop_name' => '演示小店（抖音）', 'shop_url' => 'https://demo.example.com/douyin', 'status' => 'inactive', 'created_at' => $ts, 'updated_at' => $ts],
            ]);

            $this->insert('platform_accounts', [
                ['platform' => 'taobao', 'account_name' => '演示账号（淘宝）', 'account_id' => 'demo-tb-001', 'status' => 'inactive', 'created_at' => $ts, 'updated_at' => $ts],
                ['platform' => 'jd', 'account_name' => '演示账号（京东）', 'account_id' => 'demo-jd-001', 'status' => 'inactive', 'created_at' => $ts, 'updated_at' => $ts],
            ]);
        }
    }
}
