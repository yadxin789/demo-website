<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('source_type', 16); // sales_order 销售订单 / pos_order POS 订单
            $table->unsignedBigInteger('sales_order_id')->nullable();
            $table->unsignedBigInteger('pos_order_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('warehouse_id');
            $table->date('return_date');
            $table->string('status', 16)->default('draft'); // draft 草稿 / done 已退货 / cancelled 已取消
            $table->string('reason', 255)->nullable();
            $table->string('refund_method', 16)->nullable(); // credit 冲应收 / cash 退现金 / bank 退银行
            $table->decimal('refund_amount', 18, 4)->default(0);
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'return_date'], 'sales_return_customer_idx');
            $table->foreign('sales_order_id')->references('id')->on('sales_orders')->nullOnDelete();
            $table->foreign('pos_order_id')->references('id')->on('pos_orders')->nullOnDelete();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
