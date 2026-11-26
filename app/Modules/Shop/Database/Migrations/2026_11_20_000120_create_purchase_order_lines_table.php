<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_price', 18, 4)->default(0);
            $table->decimal('discount_rate', 9, 4)->default(0); // 折扣率（%，0~100）
            $table->decimal('subtotal', 18, 4)->default(0); // 行净额 = qty × unit_price × (1 - discount_rate/100)
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->string('line_note')->nullable();
            $table->timestamps();

            $table->index(['order_id'], 'po_line_order_idx');
            $table->foreign('order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('tax_id')->references('id')->on('taxes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
