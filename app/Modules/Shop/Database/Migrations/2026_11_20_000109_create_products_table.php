<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // SKU / 内部编码
            $table->string('barcode')->nullable()->unique(); // 商品条码
            $table->string('name');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->string('cost_method', 16)->default('standard'); // standard 标准成本 / avco 移动加权（预留）/ fifo 先进先出（预留）
            $table->decimal('standard_cost', 18, 4)->default(0); // 标准成本
            $table->decimal('avg_cost', 18, 4)->default(0); // 平均成本（预留）
            $table->decimal('list_price', 18, 4)->default(0); // 零售价 / 标价
            $table->string('traceability', 16)->default('none'); // none 不追踪 / lot 批次 / serial 序列号
            $table->unsignedInteger('shelf_life_days')->nullable(); // 保质期天数
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('product_categories')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
            $table->foreign('tax_id')->references('id')->on('taxes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
