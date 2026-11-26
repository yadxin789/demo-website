<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // 客户默认折扣率（0~100），选客户时带出到整单折扣
            $table->decimal('default_discount_rate', 5, 2)->default(0)->after('credit_limit');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('default_discount_rate');
        });
    }
};
