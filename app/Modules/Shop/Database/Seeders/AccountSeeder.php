<?php

namespace App\Modules\Shop\Database\Seeders;

use App\Modules\Shop\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // 资产类（借方余额）
            ['code' => '1001', 'name' => '库存商品', 'type' => Account::TYPE_ASSET, 'side' => Account::SIDE_DEBIT],
            ['code' => '1002', 'name' => '在途物资', 'type' => Account::TYPE_ASSET, 'side' => Account::SIDE_DEBIT],
            ['code' => '1003', 'name' => '应收账款', 'type' => Account::TYPE_ASSET, 'side' => Account::SIDE_DEBIT],
            ['code' => '1004', 'name' => '库存现金', 'type' => Account::TYPE_ASSET, 'side' => Account::SIDE_DEBIT],
            ['code' => '1005', 'name' => '银行存款', 'type' => Account::TYPE_ASSET, 'side' => Account::SIDE_DEBIT],
            ['code' => '1101', 'name' => '待处理财产损溢', 'type' => Account::TYPE_ASSET, 'side' => Account::SIDE_DEBIT],
            // 负债类（贷方余额）
            ['code' => '2001', 'name' => '应付账款', 'type' => Account::TYPE_LIABILITY, 'side' => Account::SIDE_CREDIT],
            ['code' => '2002', 'name' => '应付账款-暂估', 'type' => Account::TYPE_LIABILITY, 'side' => Account::SIDE_CREDIT],
            ['code' => '2003', 'name' => '应交税费-进项税额', 'type' => Account::TYPE_LIABILITY, 'side' => Account::SIDE_CREDIT],
            ['code' => '2004', 'name' => '应交税费-销项税额', 'type' => Account::TYPE_LIABILITY, 'side' => Account::SIDE_CREDIT],
            ['code' => '2005', 'name' => '预收账款', 'type' => Account::TYPE_LIABILITY, 'side' => Account::SIDE_CREDIT],
            ['code' => '2006', 'name' => '其他应付款', 'type' => Account::TYPE_LIABILITY, 'side' => Account::SIDE_CREDIT],
            // 权益类（贷方余额）
            ['code' => '3001', 'name' => '实收资本', 'type' => Account::TYPE_EQUITY, 'side' => Account::SIDE_CREDIT],
            ['code' => '3002', 'name' => '本年利润', 'type' => Account::TYPE_EQUITY, 'side' => Account::SIDE_CREDIT],
            // 收入类（贷方余额）
            ['code' => '4001', 'name' => '主营业务收入', 'type' => Account::TYPE_INCOME, 'side' => Account::SIDE_CREDIT],
            // 费用类（借方余额）
            ['code' => '5001', 'name' => '主营业务成本', 'type' => Account::TYPE_EXPENSE, 'side' => Account::SIDE_DEBIT],
            ['code' => '5002', 'name' => '管理费用', 'type' => Account::TYPE_EXPENSE, 'side' => Account::SIDE_DEBIT],
            ['code' => '5003', 'name' => '营业外支出', 'type' => Account::TYPE_EXPENSE, 'side' => Account::SIDE_DEBIT],
            ['code' => '5004', 'name' => '销售费用', 'type' => Account::TYPE_EXPENSE, 'side' => Account::SIDE_DEBIT],
            ['code' => '5005', 'name' => '财务费用', 'type' => Account::TYPE_EXPENSE, 'side' => Account::SIDE_DEBIT],
        ];

        foreach ($accounts as $account) {
            Account::firstOrCreate(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'balance_side' => $account['side'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}
