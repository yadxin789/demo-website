<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 小票模板可配置：抬头/地址/电话/页脚与是否打印折扣、收银员、条码。
 */
return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('receipt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->default('默认模板');
            $table->string('store_name', 80);
            $table->string('address', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('footer', 160)->nullable();
            $table->boolean('show_discount')->default(true);
            $table->boolean('show_cashier')->default(true);
            $table->boolean('show_barcode')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_templates');
    }
};
