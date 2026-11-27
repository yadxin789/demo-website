<?php

declare(strict_types=1);

namespace App\Modules\Pm\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PM 岗位演示数据：让「岗位管理」后台有真实可看的岗位序列，并给演示用户挂上岗位。
 */
class OrgDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::connection('pm')->hasTable('positions')) {
            return;
        }

        $positions = [
            ['PM-ADMIN', '系统管理员', 10, 1],
            ['PM-PM', '项目经理', 1, 2],
            ['PM-PRODUCT', '产品经理', 2, 3],
            ['PM-RD', '研发工程师', 3, 4],
            ['PM-TEST', '测试工程师', 4, 5],
            ['PM-STRUCT', '结构工程师', 5, 6],
            ['PM-PROC', '工艺工程师', 6, 7],
            ['PM-DOC', '文控专员', 7, 8],
            ['PM-PURCH', '采购专员', 8, 9],
            ['PM-QA', '质量工程师', 9, 10],
        ];

        $now = now();
        foreach ($positions as [$code, $name, $deptId, $sort]) {
            $exists = DB::connection('pm')->table('positions')->where('code', $code)->exists();
            if ($exists) {
                continue;
            }
            DB::connection('pm')->table('positions')->insert([
                'code' => $code,
                'name' => $name,
                'department_id' => $deptId,
                'sort' => $sort,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::connection('pm')->hasColumn('users', 'position_id')) {
            $map = DB::connection('pm')->table('positions')->pluck('id', 'code');
            $byDept = [
                1 => 'PM-PM',
                2 => 'PM-PRODUCT',
                3 => 'PM-RD',
                4 => 'PM-TEST',
                5 => 'PM-STRUCT',
                6 => 'PM-PROC',
                7 => 'PM-DOC',
                8 => 'PM-PURCH',
                9 => 'PM-QA',
                10 => 'PM-ADMIN',
            ];
            foreach ($byDept as $deptId => $code) {
                if (empty($map[$code])) {
                    continue;
                }
                DB::connection('pm')->table('users')
                    ->where('department_id', $deptId)
                    ->whereNull('position_id')
                    ->update(['position_id' => $map[$code], 'updated_at' => $now]);
            }
            DB::connection('pm')->table('users')
                ->where('username', 'admin')
                ->update(['position_id' => $map['PM-ADMIN'] ?? null, 'updated_at' => $now]);
        }
    }
}
