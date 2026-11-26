<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::table('pos_stations', function (Blueprint $table) {
            // 结账后自动调起浏览器打印对话框（终端级开关，默认关闭，避免每单都弹系统打印框）
            $table->boolean('auto_print_receipt')->default(false)->after('receipt_template');
        });
    }

    public function down(): void
    {
        Schema::table('pos_stations', function (Blueprint $table) {
            $table->dropColumn('auto_print_receipt');
        });
    }
};
