<?php

namespace App\Modules\Shop\Database\Seeders;

use App\Modules\Shop\Models\Sequence;
use Illuminate\Database\Seeder;

class SequenceSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            ['code' => 'purchase_order', 'name' => '采购订单', 'prefix' => 'PO', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'sales_order', 'name' => '销售订单', 'prefix' => 'SO', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'pos_receipt', 'name' => 'POS 小票', 'prefix' => 'RC', 'suffix' => '', 'padding' => 4, 'period_mode' => 'day'],
            ['code' => 'voucher', 'name' => '会计凭证', 'prefix' => 'JV', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'stock_in', 'name' => '入库单', 'prefix' => 'IN', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'stock_out', 'name' => '出库单', 'prefix' => 'OUT', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'transfer', 'name' => '调拨单', 'prefix' => 'TR', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'count', 'name' => '盘点单', 'prefix' => 'CK', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'scrap', 'name' => '报废单', 'prefix' => 'SC', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'return_purchase', 'name' => '采购退货', 'prefix' => 'RP', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'return_sale', 'name' => '销售退货', 'prefix' => 'RS', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'payment', 'name' => '收付款单', 'prefix' => 'PM', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'bill', 'name' => '应付账单', 'prefix' => 'BI', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'invoice', 'name' => '应收发票', 'prefix' => 'IV', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
            ['code' => 'expense', 'name' => '费用报销单', 'prefix' => 'EX', 'suffix' => '', 'padding' => 4, 'period_mode' => 'month'],
        ];

        foreach ($rules as $rule) {
            Sequence::firstOrCreate(['code' => $rule['code']], $rule);
        }
    }
}
