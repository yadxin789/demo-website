<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('pos_session_cash_counts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->decimal('denomination', 18, 4); // 面额
            $table->unsignedInteger('count_qty')->default(0); // 张/枚数
            $table->decimal('count_amount', 18, 4)->default(0);
            $table->unsignedBigInteger('counted_by')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('pos_sessions')->cascadeOnDelete();
            $table->foreign('counted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_session_cash_counts');
    }
};
