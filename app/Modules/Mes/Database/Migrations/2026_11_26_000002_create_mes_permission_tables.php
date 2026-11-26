<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MES 权限表（M0-03）：spatie/laravel-permission 8.x 标准五张表。
 *
 * 与 ERP 的 spatie_roles 不同名：本库是独立库，表名保持 spatie 默认，
 * 因此模型必须显式设 $table 与 $guard_name，避免与 ERP 的命名混用。
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        Schema::connection('mes')->create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('mes')->create('spatie_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::connection('mes')->create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'mes_model_has_permissions_model_id_model_type_index');

            $table->foreign('permission_id')
                ->references('id')->on('permissions')
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'model_id', 'model_type'],
                'mes_model_has_permissions_permission_model_type_primary');
        });

        Schema::connection('mes')->create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type'], 'mes_model_has_roles_model_id_model_type_index');

            $table->foreign('role_id')
                ->references('id')->on('spatie_roles')
                ->cascadeOnDelete();

            $table->primary(['role_id', 'model_id', 'model_type'],
                'mes_model_has_roles_role_model_type_primary');
        });

        Schema::connection('mes')->create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->foreign('permission_id')
                ->references('id')->on('permissions')
                ->cascadeOnDelete();

            $table->foreign('role_id')
                ->references('id')->on('spatie_roles')
                ->cascadeOnDelete();

            $table->primary(['permission_id', 'role_id'],
                'mes_role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'spatie_roles', 'permissions'] as $t) {
            Schema::connection('mes')->dropIfExists($t);
        }
    }
};
