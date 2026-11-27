<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S9-07：PM 用户表补 email。
 *
 * 目的：登录支持"邮箱或用户名"，与其余四个产品口径一致（HC-42）。
 * 原 PM 表只有 username，没有 email。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'email')) {
                $table->string('email', 120)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
