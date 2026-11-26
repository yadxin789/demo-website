<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('account_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('statement_id');
            $table->unsignedBigInteger('entry_line_id');
            $table->unsignedBigInteger('account_id');
            $table->decimal('amount', 14, 4);
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('reconciled_at');
            $table->timestamps();

            $table->foreign('statement_id')->references('id')->on('bank_statements')->cascadeOnDelete();
            $table->foreign('entry_line_id')->references('id')->on('account_entry_lines')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();

            // 一对一勾对：一条银行流水、一条系统分录行只能被配对一次。
            $table->unique('statement_id');
            $table->unique('entry_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_reconciliations');
    }
};
