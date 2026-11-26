<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MES 系统表（M0-03）：账号 + 通知 + 框架基础设施表。
 *
 * HC-18：框架级表每个产品库各建一份，五库之间互不共享。
 * MC-01：表名不加 mes_ 前缀 —— 独立库，前缀无意义。
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        Schema::connection('mes')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('employee_no', 32)->nullable()->index();
            $table->string('team', 64)->nullable()->index();
            $table->string('password');
            $table->string('status', 16)->default('active')->index();
            $table->string('remember_token', 100)->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        // 缓存/队列走 file 与 sync 驱动，但保留表结构以防某次切到 database 驱动时炸库
        Schema::connection('mes')->create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::connection('mes')->create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::connection('mes')->create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::connection('mes')->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['failed_jobs', 'jobs', 'cache_locks', 'cache', 'personal_access_tokens', 'notifications', 'users'] as $t) {
            Schema::connection('mes')->dropIfExists($t);
        }
    }
};
