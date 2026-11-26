<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // 单据类型：purchase_order/sales_order/pos_receipt/voucher/stock_in/stock_out/transfer/count/return
            $table->string('name');
            $table->string('prefix', 20)->nullable()->default('');
            $table->string('suffix', 20)->nullable()->default('');
            $table->unsignedTinyInteger('padding')->default(4); // 流水位数
            $table->unsignedInteger('next_number')->default(1); // 下一可用流水号
            $table->string('period_mode', 16)->default('none'); // none 不重置 / month 按月 / year 按年
            $table->date('last_reset_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
