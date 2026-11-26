<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('account_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entry_id');
            $table->unsignedBigInteger('account_id');
            $table->string('direction', 8); // debit 借 / credit 贷
            $table->decimal('amount', 18, 4);
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('memo')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['entry_id'], 'entry_line_entry_idx');
            $table->foreign('entry_id')->references('id')->on('account_entries')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->foreign('tax_id')->references('id')->on('taxes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_entry_lines');
    }
};
