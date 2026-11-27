<?php

namespace App\Modules\Pm\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            RoleSeeder::class,
            DepartmentSeeder::class,
            TaskTypeSeeder::class,
            UserSeeder::class,
            DemoSeeder::class,
            // 演示站专用：通知/待办/文件/知识库/模板/AI 等空表补齐
            DemoCoverageSeeder::class,
            // 演示站专用：岗位序列与用户岗位归属
            OrgDemoSeeder::class,
        ]);
    }
}
