<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->unsignedBigInteger('partner_id'); // 客户
            $table->unsignedBigInteger('entry_id')->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('draft'); // draft / open / partial / paid / void
            $table->decimal('total_amount', 18, 4)->default(0);
            $table->decimal('paid_amount', 18, 4)->default(0);
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->timestamps();

            $table->foreign('partner_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->foreign('entry_id')->references('id')->on('account_entries')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
