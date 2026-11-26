<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('lot_no', 50);
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->date('receipt_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('status', 16)->default('active'); // active 在库 / expired 过期 / closed 结清
            $table->timestamps();

            $table->unique(['product_id', 'lot_no'], 'lot_product_no_idx');
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
