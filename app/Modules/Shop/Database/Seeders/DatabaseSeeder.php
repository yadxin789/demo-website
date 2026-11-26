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
        ]);
    }
}
