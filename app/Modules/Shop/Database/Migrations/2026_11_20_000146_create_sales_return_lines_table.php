<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('sales_return_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('return_id');
            $table->unsignedBigInteger('sales_order_line_id')->nullable();
            $table->unsignedBigInteger('pos_order_line_id')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_price', 18, 4)->default(0); // 折后单价（净收入口径）
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4)->default(0); // 净额 + 税额
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['return_id'], 'sales_return_line_return_idx');
            $table->index(['sales_order_line_id'], 'sales_return_line_so_line_idx');
            $table->index(['pos_order_line_id'], 'sales_return_line_pos_line_idx');
            $table->foreign('return_id')->references('id')->on('sales_returns')->cascadeOnDelete();
            $table->foreign('sales_order_line_id')->references('id')->on('sales_order_lines')->nullOnDelete();
            $table->foreign('pos_order_line_id')->references('id')->on('pos_order_lines')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_lines');
    }
};
