<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('station_id');
            $table->unsignedBigInteger('cashier_id');
            $table->timestamp('opened_at');
            $table->decimal('opened_balance', 18, 4)->default(0); // 期初现金
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 8)->default('open'); // open / closed
            $table->decimal('expected_cash', 18, 4)->nullable(); // 交班应收现金
            $table->decimal('counted_cash', 18, 4)->nullable(); // 实点现金
            $table->decimal('variance_amount', 18, 4)->nullable(); // 长短款
            $table->decimal('total_sales', 18, 4)->default(0);
            $table->decimal('total_refund', 18, 4)->default(0);
            $table->decimal('total_discount', 18, 4)->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('station_id')->references('id')->on('pos_stations');
            $table->foreign('cashier_id')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};
