<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->string('lot_no')->nullable()->after('product_id');
            $table->string('serial_no')->nullable()->after('lot_no');
        });

        Schema::table('pos_order_lines', function (Blueprint $table) {
            $table->string('serial_no')->nullable()->after('lot_no');
        });
    }

    public function down(): void
    {
        Schema::table('sales_order_lines', function (Blueprint $table) {
            $table->dropColumn(['lot_no', 'serial_no']);
        });

        Schema::table('pos_order_lines', function (Blueprint $table) {
            $table->dropColumn('serial_no');
        });
    }
};
