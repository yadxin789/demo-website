<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('pos_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('lot_no')->nullable();
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_price', 18, 4); // 成交单价（可能被手工改价）
            $table->decimal('original_price', 18, 4); // 原始零售价留痕
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->text('line_note')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('pos_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->foreign('tax_id')->references('id')->on('taxes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_order_lines');
    }
};
