<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->default('system');
            $table->string('level', 16)->default('info');
            $table->string('title', 150);
            $table->text('content')->nullable();
            $table->string('action_route', 120)->nullable();
            $table->string('dedupe_key', 191)->nullable()->unique();
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_read'], 'notifications_user_read_idx');
            $table->index(['user_id', 'created_at'], 'notifications_user_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
