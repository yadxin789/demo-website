<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('session_id')->nullable();
            $table->unsignedBigInteger('method_id');
            $table->decimal('amount', 18, 4);
            $table->string('reference')->nullable(); // 交易号/小票号/备注
            $table->timestamp('paid_at');
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('pos_orders')->cascadeOnDelete();
            $table->foreign('session_id')->references('id')->on('pos_sessions')->nullOnDelete();
            $table->foreign('method_id')->references('id')->on('payment_methods');
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
    }
};
