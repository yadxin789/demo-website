<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->string('type', 16); // asset 资产 / liability 负债 / equity 权益 / income 收入 / expense 费用
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('balance_side', 8); // debit 借方 / credit 贷方
            $table->boolean('is_system')->default(false); // 系统科目不可删除
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
