<?php

namespace App\Modules\Shop\Database\Seeders;

use App\Modules\Shop\Models\PaymentGatewaySetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 演示数据覆盖（Shop 侧）：补齐参考项目种子没覆盖的空表。
 *
 * 覆盖：费用单 / 应付账单明细 / 上架流水 / 支付网关配置 / AI 会话。
 * 全部为虚拟演示数据；幂等：先判重再插入，可重复执行。
 */
class DemoCoverageSeeder extends Seeder
{
    private string $conn = 'shop';

    public function run(): void
    {
        $this->seedExpenses();
        $this->seedBillLines();
        $this->seedPutawayMovements();
        $this->seedPaymentGateways();
        $this->seedAi();
        $this->seedOpenPosSession();
    }

    /**
     * 给演示账号开一个当日 POS 班次：否则收银台相关接口全是 403（未开班），
     * 客户点进来看不到 POS 销售流程。
     */
    private function seedOpenPosSession(): void
    {
        if (! Schema::connection($this->conn)->hasTable('pos_sessions')
            || ! Schema::connection($this->conn)->hasTable('pos_stations')) {
            return;
        }
        $stationId = (int) (DB::connection($this->conn)->table('pos_stations')->min('id') ?? 0);
        if ($stationId === 0) {
            return;
        }
        foreach ([1, 3] as $cashierId) { // 1=超级管理员，3=演示收银员
            $exists = DB::connection($this->conn)->table('pos_sessions')
                ->where('station_id', $stationId)->where('cashier_id', $cashierId)
                ->where('status', 'open')->exists();
            if ($exists) {
                continue;
            }
            $this->insert('pos_sessions', [[
                'station_id' => $stationId,
                'cashier_id' => $cashierId,
                'opened_at' => $this->ts(0, '08:30:00'),
                'opened_balance' => 200,
                'closed_at' => null,
                'status' => 'open',
                'expected_cash' => null,
                'counted_cash' => null,
                'variance_amount' => null,
                'total_sales' => 0,
                'total_refund' => 0,
                'total_discount' => 0,
                'note' => '演示：当日开班',
                'created_at' => $this->ts(0, '08:30:00'),
                'updated_at' => $this->now(),
            ]]);
        }
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

    private function has(string $table, array $where): bool
    {
        if (! Schema::connection($this->conn)->hasTable($table)) {
            return false;
        }

        return DB::connection($this->conn)->table($table)->where($where)->exists();
    }

    private function day(int $offset): string
    {
        return now()->addDays($offset)->format('Y-m-d');
    }

    private function ts(int $offset, string $time = '09:00:00'): string
    {
        return now()->addDays($offset)->format('Y-m-d') . ' ' . $time;
    }

    private function now(): string
    {
        return now()->format('Y-m-d H:i:s');
    }

    // ----------------------------------------------------------- 费用单

    /**
     * 费用单页需要 expense_account_id（费用科目）与 payment_account_id（付款科目），
     * 取参考项目已建好的会计科目：5002 管理费用 / 5004 销售费用 / 5005 财务费用 + 1005 银行存款。
     */
    private function seedExpenses(): void
    {
        if ($this->has('expenses', ['code' => 'EXP-DEMO-0001'])) {
            return;
        }
        $ts = $this->now();
        $rows = [
            ['EXP-DEMO-0001', -20, 17, '演示物业公司', '门店', 'bank', 5, 3200, 0, 3200, 'paid', '门店 11 月物业费'],
            ['EXP-DEMO-0002', -15, 19, '演示广告服务商', '市场部', 'bank', 5, 8600, 516, 9116, 'confirmed', '门店开业宣传物料'],
            ['EXP-DEMO-0003', -10, 20, '演示银行', '财务部', 'bank', 5, 120, 0, 120, 'confirmed', '账户管理费'],
            ['EXP-DEMO-0004', -6, 17, '演示水电公司', '门店', 'cash', 4, 1860, 0, 1860, 'paid', '门店水电费'],
            ['EXP-DEMO-0005', -2, 19, '演示培训机构', '人事部', 'payable', null, 4200, 252, 4452, 'draft', '收银员服务培训'],
        ];
        $data = [];
        foreach ($rows as [$code, $dateOff, $acc, $payee, $dept, $method, $payAcc, $net, $tax, $total, $status, $desc]) {
            $data[] = [
                'code' => $code,
                'expense_date' => $this->day($dateOff),
                'expense_account_id' => $acc,
                'partner_id' => null,
                'payee' => $payee,
                'department' => $dept,
                'settlement_method' => $method,
                'payment_account_id' => $payAcc,
                'amount_net' => $net,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'status' => $status,
                'description' => $desc,
                'reference' => null,
                'creator_id' => 1,
                'entry_id' => null,
                'paid_entry_id' => null,
                'paid_at' => $status === 'paid' ? $this->ts($dateOff, '15:00:00') : null,
                'created_at' => $this->ts($dateOff),
                'updated_at' => $ts,
            ];
        }
        $this->insert('expenses', $data);
    }

    // --------------------------------------------------------- 应付账单明细

    private function seedBillLines(): void
    {
        if (DB::connection($this->conn)->table('bill_lines')->count() > 0) {
            return;
        }
        $bills = DB::connection($this->conn)->table('bills')->orderBy('id')->limit(6)->get(['id', 'total_amount']);
        $productIds = DB::connection($this->conn)->table('products')->orderBy('id')->limit(6)->pluck('id')->all();
        if ($bills->isEmpty() || $productIds === []) {
            return;
        }
        $ts = $this->now();
        $rows = [];
        foreach ($bills as $i => $bill) {
            $amount = round(((float) $bill->total_amount) / 2, 2);
            foreach ([0, 1] as $n) {
                $productId = $productIds[($i + $n) % count($productIds)];
                $qty = 20 + $i * 5;
                $unit = round($amount / $qty, 2);
                $lineAmount = round($qty * $unit, 2);
                $rows[] = [
                    'bill_id' => $bill->id,
                    'purchase_order_line_id' => null,
                    'product_id' => $productId,
                    'qty' => $qty,
                    'unit_price' => $unit,
                    'amount' => $lineAmount,
                    'tax_amount' => round($lineAmount * 0.13, 2),
                    'line_note' => $n === 0 ? '演示采购明细' : '演示赠品明细',
                    'created_at' => $ts,
                    'updated_at' => $ts,
                ];
            }
        }
        $this->insert('bill_lines', $rows);
    }

    // ------------------------------------------------------------ 上架流水

    private function seedPutawayMovements(): void
    {
        $exists = DB::connection($this->conn)->table('stock_movements')
            ->where('source_type', 'putaway')->exists();
        if ($exists) {
            return;
        }
        $products = DB::connection($this->conn)->table('products')->orderBy('id')->limit(6)->get(['id', 'standard_cost']);
        $locations = DB::connection($this->conn)->table('locations')->orderBy('id')->limit(6)->get(['id', 'warehouse_id']);
        if ($products->isEmpty() || $locations->isEmpty()) {
            return;
        }
        $rows = [];
        foreach ($products as $i => $p) {
            $loc = $locations[$i % count($locations)];
            $qty = 30 + $i * 10;
            $rows[] = [
                'product_id' => $p->id,
                'warehouse_id' => $loc->warehouse_id,
                'location_id' => $loc->id,
                'lot_no' => null,
                'serial_no' => null,
                'direction' => 'in',
                'qty_before' => 0,
                'qty_after' => $qty,
                'qty' => $qty,
                'unit_cost' => (float) $p->standard_cost,
                'movement_date' => $this->ts(-3 + intdiv($i, 3), '10:20:00'),
                'source_type' => 'putaway',
                'source_id' => null,
                'source_code' => 'PA-DEMO-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'operator_id' => 1,
                'note' => '演示：收货后上架',
                'created_at' => $this->ts(-3 + intdiv($i, 3), '10:20:00'),
            ];
        }
        $this->insert('stock_movements', $rows);
    }

    // ---------------------------------------------------- 支付网关配置

    private function seedPaymentGateways(): void
    {
        if (! Schema::connection($this->conn)->hasTable('payment_gateway_settings')) {
            return;
        }
        if (PaymentGatewaySetting::query()->where('provider', 'wechat')->exists()) {
            return;
        }
        // 密钥字段是 encrypted cast，必须经模型写入，直插明文会在读取时炸
        PaymentGatewaySetting::create([
            'provider' => 'wechat',
            'app_id' => 'demo-wx-appid',
            'merchant_id' => 'demo-wx-mchid',
            'api_secret' => 'demo-wx-secret',
            'private_key' => null,
            'public_key' => null,
            'cert_serial' => null,
            'gateway_url' => 'https://api.mch.weixin.qq.com',
            'last_checked_at' => $this->ts(-1, '08:30:00'),
            'last_check_status' => PaymentGatewaySetting::STATUS_PASS,
            'last_check_message' => '演示环境：签名校验通过（未发起真实请求）',
        ]);

        PaymentGatewaySetting::create([
            'provider' => 'alipay',
            'app_id' => 'demo-alipay-appid',
            'merchant_id' => null,
            'api_secret' => 'demo-alipay-secret',
            'private_key' => null,
            'public_key' => null,
            'cert_serial' => null,
            'gateway_url' => 'https://openapi.alipay.com',
            'last_checked_at' => $this->ts(-1, '08:31:00'),
            'last_check_status' => PaymentGatewaySetting::STATUS_FAIL,
            'last_check_message' => '演示环境：商户号未配置（示例失败态）',
        ]);
    }

    // ---------------------------------------------------------------- AI

    private function seedAi(): void
    {
        if (! Schema::connection($this->conn)->hasTable('ai_conversations')
            || $this->has('ai_conversations', ['title' => '演示会话：今天卖得怎么样'])) {
            return;
        }
        $conversations = [
            ['演示会话：今天卖得怎么样', '今天门店销售额和客单价是多少？', '今日销售额 12,860 元，客单价 68.4 元，环比昨日 +9.2%。'],
            ['演示会话：滞销商品怎么处理', '有哪些商品 30 天没动销？', '建议关注 3 个 SKU，可考虑组合促销或调拨到总仓。'],
        ];
        foreach ($conversations as $ci => [$title, $q, $a]) {
            $convId = (int) DB::connection($this->conn)->table('ai_conversations')->insertGetId([
                'user_id' => 1,
                'title' => $title,
                'message_count' => 2,
                'last_message_at' => $this->ts(-1, '17:0' . $ci . ':00'),
                'created_at' => $this->ts(-1),
                'updated_at' => $this->ts(-1),
            ]);
            $this->insert('ai_messages', [
                ['conversation_id' => $convId, 'role' => 'user', 'content' => $q, 'created_at' => $this->ts(-1, '16:58:00'), 'updated_at' => $this->ts(-1, '16:58:00')],
                ['conversation_id' => $convId, 'role' => 'assistant', 'content' => $a, 'created_at' => $this->ts(-1, '17:0' . $ci . ':00'), 'updated_at' => $this->ts(-1, '17:0' . $ci . ':00')],
            ]);
        }
    }
}
