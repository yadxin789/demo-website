<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR 权限表（H0-01）：spatie/laravel-permission 8.x 标准五张表。
 *
 * 注意：config/permission.php 全局把 roles 表名设成 spatie_roles（与 ERP 共用命名空间，
 * 但每个产品库各有一张，互不共享）。本迁移必须与之对齐。
 */
return new class extends Migration
{
    protected $connection = 'hr';

    public function up(): void
    {
        Schema::connection('hr')->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('hr')->create('spatie_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('hr')->create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'hr_model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')
                ->references('id')->on('permissions')
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'model_id', 'model_type'],
                'hr_model_has_permissions_permission_model_type_primary');
        });

        Schema::connection('hr')->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'hr_model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')
                ->references('id')->on('spatie_roles')
                ->cascadeOnDelete();

            $table->primary(['role_id', 'model_id', 'model_type'],
                'hr_model_has_roles_role_model_type_primary');
        });

        Schema::connection('hr')->create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')->on('permissions')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')->on('spatie_roles')
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'],
                'hr_role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'spatie_roles', 'permissions'] as $t) {
            Schema::connection('hr')->dropIfExists($t);
        }
    }
};
