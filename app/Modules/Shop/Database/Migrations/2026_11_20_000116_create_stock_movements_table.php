<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->string('direction', 16); // in 入库 / out 出库 / transfer 调拨 / adjust 盘点调整
            $table->decimal('qty_before', 18, 4); // 变动前数量
            $table->decimal('qty_after', 18, 4); // 变动后数量
            $table->decimal('qty', 18, 4); // 本次变动数量（入库为正，出库为负）
            $table->decimal('unit_cost', 18, 4)->default(0); // 单位标准成本
            $table->dateTime('movement_date');
            $table->string('source_type', 30)->nullable(); // receipt / sales_order / pos_order / transfer / count / scrap
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_code', 50)->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['product_id', 'movement_date'], 'move_product_idx');
            $table->index(['location_id', 'movement_date'], 'move_loc_idx');
            $table->index('source_code', 'move_source_idx');

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
