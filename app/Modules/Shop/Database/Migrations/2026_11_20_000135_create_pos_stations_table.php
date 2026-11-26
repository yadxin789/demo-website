<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('pos_stations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->unsignedBigInteger('warehouse_id');
            $table->unsignedBigInteger('default_location_id')->nullable();
            $table->json('payment_method_ids')->nullable(); // 可用支付方式
            $table->string('receipt_template', 32)->default('default');
            $table->string('printer_driver', 16)->default('escpos'); // escpos / browser
            $table->string('printer_address')->nullable(); // 代理地址或设备地址
            $table->string('printer_port')->nullable();
            $table->unsignedTinyInteger('paper_width')->default(58);
            $table->string('encoding', 16)->default('gbk');
            $table->string('cash_drawer_pin')->nullable(); // 钱箱脉冲引脚
            $table->string('status', 16)->default('active'); // active 启用 / disabled 停用
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('default_location_id')->references('id')->on('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_stations');
    }
};
