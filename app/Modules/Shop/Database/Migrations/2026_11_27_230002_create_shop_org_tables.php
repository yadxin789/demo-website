<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop 组织架构表（部门 / 岗位）：百货零售后台同样需要用户、部门、岗位、角色四件套。
 * 只建 Shop 自己的表，不碰其它产品库。
 */
return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        if (! Schema::connection('shop')->hasTable('departments')) {
            Schema::connection('shop')->create('departments', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 32)->nullable();
                $table->string('name', 80);
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->string('status', 16)->default('active');
                $table->timestamps();
                $table->index(['parent_id', 'sort']);
            });
        }

        if (! Schema::connection('shop')->hasTable('positions')) {
            Schema::connection('shop')->create('positions', function (Blueprint $table): void {
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

        Schema::connection('shop')->table('users', function (Blueprint $table): void {
            if (! Schema::connection('shop')->hasColumn('users', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable()->after('phone');
            }
            if (! Schema::connection('shop')->hasColumn('users', 'position_id')) {
                $table->unsignedBigInteger('position_id')->nullable()->after('department_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('shop')->table('users', function (Blueprint $table): void {
            if (Schema::connection('shop')->hasColumn('users', 'position_id')) {
                $table->dropColumn('position_id');
            }
            if (Schema::connection('shop')->hasColumn('users', 'department_id')) {
                $table->dropColumn('department_id');
            }
        });

        Schema::connection('shop')->dropIfExists('positions');
        Schema::connection('shop')->dropIfExists('departments');
    }
};
