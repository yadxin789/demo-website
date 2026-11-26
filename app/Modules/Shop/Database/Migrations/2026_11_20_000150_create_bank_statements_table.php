<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->date('statement_date');
            $table->string('direction', 16); // receive 入账 / pay 出账
            $table->decimal('amount', 14, 4);
            $table->string('counterparty', 255)->nullable();
            $table->string('reference', 64)->nullable();
            $table->string('memo', 255)->nullable();
            $table->string('status', 16)->default('unmatched'); // unmatched 未勾对 / matched 已勾对
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['account_id', 'statement_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
    }
};
