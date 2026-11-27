<?php

namespace App\Modules\Shop\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RbacSeeder::class,
            DemoUserSeeder::class,
            SequenceSeeder::class,
            AccountSeeder::class,
            JournalSeeder::class,
            // 演示站专用：费用单 / 账单明细 / 上架流水 / 支付网关 / AI 会话
            DemoCoverageSeeder::class,
            // 演示站专用：部门 / 岗位 / 用户归属
            OrgDemoSeeder::class,
        ]);
    }
}
