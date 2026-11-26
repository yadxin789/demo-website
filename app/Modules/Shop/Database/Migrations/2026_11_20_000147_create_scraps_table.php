<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('scraps', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->unsignedBigInteger('warehouse_id');
            $table->date('scrap_date');
            $table->string('status', 16)->default('draft'); // draft 草稿 / done 已报废 / cancelled 已取消
            $table->string('loss_type', 16)->default('expense'); // expense 管理费用 / other 营业外支出
            $table->string('reason', 255);
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->cascadeOnDelete();
            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraps');
    }
};
