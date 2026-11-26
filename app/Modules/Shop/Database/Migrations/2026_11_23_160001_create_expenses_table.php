<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->date('expense_date');
            $table->unsignedBigInteger('expense_account_id');
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('payee', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('settlement_method', 16)->default('payable'); // payable 挂账 / cash 现金 / bank 银行
            $table->unsignedBigInteger('payment_account_id')->nullable();
            $table->decimal('amount_net', 18, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->string('status', 16)->default('draft'); // draft 草稿 / confirmed 已确认 / paid 已付款 / void 已作废
            $table->text('description')->nullable();
            $table->string('reference', 64)->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->unsignedBigInteger('entry_id')->nullable();
            $table->unsignedBigInteger('paid_entry_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->foreign('expense_account_id')->references('id')->on('accounts');
            $table->foreign('payment_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('creator_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('entry_id')->references('id')->on('account_entries')->nullOnDelete();
            $table->foreign('paid_entry_id')->references('id')->on('account_entries')->nullOnDelete();
        });

        Schema::create('expense_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('expense_id');
            $table->string('description', 255);
            $table->decimal('amount_net', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('total_amount', 18, 4);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->foreign('expense_id')->references('id')->on('expenses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_lines');
        Schema::dropIfExists('expenses');
    }
};
