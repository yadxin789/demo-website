<?php

declare(strict_types=1);

namespace App\Modules\Shop\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop 组织架构演示数据：部门 / 岗位 / 用户归属。
 * 百货零售侧的组织口径：总部 + 门店运营 + 采购 + 仓储物流 + 财务 + 信息。
 */
class OrgDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::connection('shop')->hasTable('departments')) {
            return;
        }

        $now = now();

        $departments = [
            ['HQ', '总部', null, 1],
            ['STORE', '门店运营部', null, 2],
            ['PURCH', '采购部', null, 3],
            ['WMS', '仓储物流部', null, 4],
            ['FIN', '财务部', null, 5],
            ['IT', '信息部', null, 6],
        ];

        $deptIds = [];
        foreach ($departments as [$code, $name, $parent, $sort]) {
            $id = DB::connection('shop')->table('departments')->where('code', $code)->value('id');
            if ($id) {
                $deptIds[$code] = (int) $id;
                continue;
            }
            $deptIds[$code] = (int) DB::connection('shop')->table('departments')->insertGetId([
                'code' => $code,
                'name' => $name,
                'parent_id' => $parent,
                'sort' => $sort,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::connection('shop')->hasTable('positions')) {
            $positions = [
                ['SH-IT', '系统管理员', 'IT', 1],
                ['SH-GM', '总经理', 'HQ', 2],
                ['SH-SM', '店长', 'STORE', 3],
                ['SH-ASM', '副店长', 'STORE', 4],
                ['SH-CASH', '收银员', 'STORE', 5],
                ['SH-SHELF', '理货员', 'STORE', 6],
                ['SH-STOCK', '库管员', 'WMS', 7],
                ['SH-BUY', '采购专员', 'PURCH', 8],
                ['SH-ACC', '会计', 'FIN', 9],
            ];
            $posIds = [];
            foreach ($positions as [$code, $name, $deptCode, $sort]) {
                $id = DB::connection('shop')->table('positions')->where('code', $code)->value('id');
                if (! $id) {
                    $id = DB::connection('shop')->table('positions')->insertGetId([
                        'code' => $code,
                        'name' => $name,
                        'department_id' => $deptIds[$deptCode] ?? null,
                        'sort' => $sort,
                        'status' => 'active',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                $posIds[$code] = (int) $id;
            }

            if (Schema::connection('shop')->hasColumn('users', 'department_id')) {
                $assign = [
                    'admin' => ['IT', 'SH-IT'],
                    'cashier' => ['STORE', 'SH-CASH'],
                    'store_manager' => ['STORE', 'SH-SM'],
                    'warehouse' => ['WMS', 'SH-STOCK'],
                    'purchasing' => ['PURCH', 'SH-BUY'],
                    'finance' => ['FIN', 'SH-ACC'],
                    'shelf' => ['STORE', 'SH-SHELF'],
                    'assistant_manager' => ['STORE', 'SH-ASM'],
                ];
                foreach ($assign as $username => [$deptCode, $posCode]) {
                    DB::connection('shop')->table('users')->where('username', $username)->update([
                        'department_id' => $deptIds[$deptCode] ?? null,
                        'position_id' => $posIds[$posCode] ?? null,
                        'updated_at' => $now,
                    ]);
                }

                // 兜底：未命中的用户挂到门店运营部
                DB::connection('shop')->table('users')->whereNull('department_id')->update([
                    'department_id' => $deptIds['STORE'] ?? null,
                    'updated_at' => $now,
                ]);
            }
        }
    }
}
