<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 库位条码：用于上架、调拨、盘点时扫描库位。
 * 既有库位回填 LOC-{id}，保证可直接打印并扫描；新库位由应用自动生成。
 */
return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('barcode', 64)->nullable()->unique()->after('code');
        });

        DB::table('locations')->orderBy('id')->get(['id'])->each(function ($location) {
            DB::table('locations')->where('id', $location->id)->update([
                'barcode' => 'LOC-' . $location->id,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['barcode']);
            $table->dropColumn('barcode');
        });
    }
};
