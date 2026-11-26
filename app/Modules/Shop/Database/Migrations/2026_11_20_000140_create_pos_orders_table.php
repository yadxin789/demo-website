<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('station_id');
            $table->unsignedBigInteger('cashier_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->timestamp('order_date');
            $table->string('status', 16)->default('draft'); // draft 草稿 / completed 已结账 / refunded 已退款
            $table->decimal('total_qty', 18, 4)->default(0);
            $table->decimal('subtotal', 18, 4)->default(0); // 行折后小计（税前）
            $table->decimal('discount_amount', 18, 4)->default(0); // 行折扣 + 整单折扣
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('grand_total', 18, 4)->default(0);
            $table->decimal('paid_amount', 18, 4)->default(0); // 实收合计
            $table->decimal('change_amount', 18, 4)->default(0); // 找零
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('pos_sessions');
            $table->foreign('station_id')->references('id')->on('pos_stations');
            $table->foreign('cashier_id')->references('id')->on('users');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_orders');
    }
};
