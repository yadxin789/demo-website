<?php

namespace App\Modules\Shop\Database\Seeders;

use App\Modules\Shop\Models\Account;
use App\Modules\Shop\Models\Journal;
use Illuminate\Database\Seeder;

class JournalSeeder extends Seeder
{
    public function run(): void
    {
        $journals = [
            ['code' => 'SALE', 'name' => '销售日记账', 'type' => Journal::TYPE_SALE, 'account_code' => '1003'],
            ['code' => 'PURCHASE', 'name' => '采购日记账', 'type' => Journal::TYPE_PURCHASE, 'account_code' => '2001'],
            ['code' => 'CASH', 'name' => '现金日记账', 'type' => Journal::TYPE_CASH, 'account_code' => '1004'],
            ['code' => 'BANK', 'name' => '银行日记账', 'type' => Journal::TYPE_BANK, 'account_code' => '1005'],
            ['code' => 'MISC', 'name' => '杂项日记账', 'type' => Journal::TYPE_MISC, 'account_code' => null],
        ];

        foreach ($journals as $journal) {
            $account = $journal['account_code']
                ? Account::where('code', $journal['account_code'])->first()
                : null;

            Journal::firstOrCreate(
                ['code' => $journal['code']],
                [
                    'name' => $journal['name'],
                    'type' => $journal['type'],
                    'account_id' => $account?->id,
                    'is_active' => true,
                ]
            );
        }
    }
}
