<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20)->unique(); // wechat / alipay
            $table->string('app_id', 80)->nullable();
            $table->string('merchant_id', 80)->nullable();
            // 加密字段：API 密钥 / 商户或应用私钥 / 平台公钥
            $table->text('api_secret')->nullable();
            $table->text('private_key')->nullable();
            $table->text('public_key')->nullable();
            $table->string('cert_serial', 80)->nullable();
            $table->string('gateway_url', 255)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_check_status', 16)->nullable(); // pass / fail
            $table->string('last_check_message', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
