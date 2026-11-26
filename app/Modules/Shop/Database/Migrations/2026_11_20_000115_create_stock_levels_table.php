<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->decimal('qty', 18, 4)->default(0); // 现存量
            $table->decimal('reserved_qty', 18, 4)->default(0); // 预留量（未发货占用）
            $table->decimal('avg_cost', 18, 4)->default(0); // 单位成本（标准成本法下取商品标准成本）
            $table->timestamps();

            // 唯一维度：商品 + 库位 + 批次 + 序列号
            $table->unique(
                ['product_id', 'location_id', 'lot_no', 'serial_no'],
                'level_dimension_idx'
            );
            $table->index(['warehouse_id', 'location_id'], 'level_wh_loc_idx');

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
