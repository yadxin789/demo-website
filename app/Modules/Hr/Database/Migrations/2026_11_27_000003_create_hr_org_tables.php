<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR 组织与编制（H1 域）：部门树、岗位、编制。
 *
 * HC-M02：部门 D001–D0xx；岗位 P001–P0xx。
 * 部门用 parent_id 自关联建多级树（集团 → 中心 → 部门）。
 */
return new class extends Migration
{
    protected $connection = 'hr';

    public function up(): void
    {
        Schema::connection('hr')->create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 64);
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->unsignedBigInteger('manager_id')->nullable()->index();
            $table->unsignedTinyInteger('level')->default(1)->index();
            $table->string('status', 16)->default('active')->index();
            $table->integer('sort')->default(0);
            $table->string('remark', 255)->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 64);
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->string('grade', 16)->nullable()->index();   // 职级：P3/P5/M2 之类
            $table->string('status', 16)->default('active')->index();
            $table->string('remark', 255)->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('headcounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->index();
            $table->unsignedBigInteger('position_id')->index();
            $table->integer('planned')->default(0);   // 编制
            $table->integer('filled')->default(0);    // 在职
            $table->timestamps();
            $table->unique(['department_id', 'position_id'], 'hr_headcounts_dept_position_unique');
        });
    }

    public function down(): void
    {
        foreach (['headcounts', 'positions', 'departments'] as $t) {
            Schema::connection('hr')->dropIfExists($t);
        }
    }
};
