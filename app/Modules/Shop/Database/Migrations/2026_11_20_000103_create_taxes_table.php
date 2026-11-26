<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('rate', 8, 4)->default(0); // 税率百分比（0 ~ 100）
            $table->string('type', 16)->default('exclusive'); // exclusive 价外(不含税) / inclusive 价内(含税)
            $table->unsignedBigInteger('account_id')->nullable(); // 应交税费科目，科目表建成后回填外键
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxes');
    }
};
