<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->unsignedBigInteger('partner_id'); // 客户（收款）或供应商（付款）
            $table->string('direction', 8); // receive 收款 / pay 付款
            $table->unsignedBigInteger('method_id');
            $table->unsignedBigInteger('account_id'); // 资金科目（库存现金/银行存款）
            $table->decimal('amount', 18, 4);
            $table->date('payment_date');
            $table->string('reference')->nullable();
            $table->string('status', 16)->default('confirmed'); // draft / confirmed
            $table->unsignedBigInteger('entry_id')->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->timestamps();

            $table->foreign('method_id')->references('id')->on('payment_methods');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('entry_id')->references('id')->on('account_entries')->nullOnDelete();
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
