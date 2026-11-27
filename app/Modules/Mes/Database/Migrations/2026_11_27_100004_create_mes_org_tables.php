<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S12-M01：MES 组织架构表（部门 / 岗位），并把用户挂到部门与岗位。
 *
 * 目的：MES 要有与 HR、ERP 同等的后台管理能力（用户 / 部门 / 岗位 / 角色），
 * 否则"完备的独立 MES"在客户眼里只是车间看板（HC-39）。
 *
 * 只建本产品自己的表，不碰 ERP / HR 库（HC-11）。
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        if (! Schema::connection('mes')->hasTable('departments')) {
            Schema::connection('mes')->create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 80);
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->string('status', 16)->default('active');   // active / disabled
                $table->timestamps();
                $table->index(['parent_id', 'sort']);
            });
        }

        if (! Schema::connection('mes')->hasTable('positions')) {
            Schema::connection('mes')->create('positions', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 80);
                $table->unsignedBigInteger('department_id')->nullable();
                $table->string('status', 16)->default('active');
                $table->timestamps();
                $table->index('department_id');
            });
        }

        Schema::connection('mes')->table('users', function (Blueprint $table): void {
            if (! Schema::connection('mes')->hasColumn('users', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('team');
            }
            if (! Schema::connection('mes')->hasColumn('users', 'position_id')) {
                $table->unsignedBigInteger('position_id')->nullable()->after('department_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mes')->table('users', function (Blueprint $table): void {
            if (Schema::connection('mes')->hasColumn('users', 'position_id')) {
                $table->dropColumn('position_id');
            }
            if (Schema::connection('mes')->hasColumn('users', 'department_id')) {
                $table->dropColumn('department_id');
            }
        });

        Schema::connection('mes')->dropIfExists('positions');
        Schema::connection('mes')->dropIfExists('departments');
    }
};
