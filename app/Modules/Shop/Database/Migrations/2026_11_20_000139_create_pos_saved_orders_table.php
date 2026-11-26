<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('pos_saved_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('station_id');
            $table->unsignedBigInteger('cashier_id');
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->json('cart_json'); // 购物车行快照（不含库存扣减）
            $table->timestamp('saved_at');
            $table->timestamp('expired_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('station_id')->references('id')->on('pos_stations');
            $table->foreign('cashier_id')->references('id')->on('users');
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();

            $table->index(['station_id', 'cashier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_saved_orders');
    }
};
