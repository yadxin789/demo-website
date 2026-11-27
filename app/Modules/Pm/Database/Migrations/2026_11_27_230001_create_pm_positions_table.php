<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PM 岗位表：让研发项目管理拥有与其它产品同等的「部门 / 岗位 / 角色」后台能力。
 * 只建 PM 自己的表，不碰其它产品库。
 */
return new class extends Migration
{
    protected $connection = 'pm';

    public function up(): void
    {
        if (! Schema::connection('pm')->hasTable('positions')) {
            Schema::connection('pm')->create('positions', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 32)->nullable();
                $table->string('name', 80);
                $table->unsignedBigInteger('department_id')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->string('status', 16)->default('active');
                $table->timestamps();
                $table->index('department_id');
            });
        }

        Schema::connection('pm')->table('users', function (Blueprint $table): void {
            if (! Schema::connection('pm')->hasColumn('users', 'position_id')) {
                $table->unsignedBigInteger('position_id')->nullable()->after('department_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('pm')->table('users', function (Blueprint $table): void {
            if (Schema::connection('pm')->hasColumn('users', 'position_id')) {
                $table->dropColumn('position_id');
            }
        });

        Schema::connection('pm')->dropIfExists('positions');
    }
};
