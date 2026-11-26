<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('count_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->decimal('book_qty', 18, 4)->default(0); // 账面数量
            $table->decimal('counted_qty', 18, 4)->nullable(); // 实盘数量（未录为 null）
            $table->decimal('variance_qty', 18, 4)->default(0); // 盈亏数量 = 实盘 - 账面
            $table->decimal('unit_cost', 18, 4)->default(0); // 标准成本
            $table->decimal('variance_value', 18, 4)->default(0); // 盈亏金额 = 盈亏数量 × 标准成本
            $table->timestamp('adjusted_at')->nullable();
            $table->timestamps();

            $table->index(['count_id'], 'count_line_count_idx');
            $table->foreign('count_id')->references('id')->on('stock_counts')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
    }
};
