<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S9-08：MES 用户表补 username。
 *
 * 原 MES 只有 email（登录时"邮箱或姓名"），补 username 后统一为"邮箱或用户名"（HC-42）。
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        Schema::connection('mes')->table('users', function (Blueprint $table): void {
            if (! Schema::connection('mes')->hasColumn('users', 'username')) {
                $table->string('username', 60)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mes')->table('users', function (Blueprint $table): void {
            if (Schema::connection('mes')->hasColumn('users', 'username')) {
                $table->dropColumn('username');
            }
        });
    }
};
