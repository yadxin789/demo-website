<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR 培训 / 技能证书 / 排班（M7 增补）。
 *
 * 对标 OrangeHRM 的 Training、Qualifications（Skills / Licenses）与 Time → Work Shift 排班，
 * 以及 Odoo HR 的 Courses / Skills / Planning 三个模块。
 */
return new class extends Migration
{
    protected $connection = 'hr';

    public function up(): void
    {
        Schema::connection('hr')->create('training_courses', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();          // TC-001
            $table->string('title', 96);
            $table->string('category', 32)->default('通用'); // 安全/技能/管理/合规
            $table->unsignedSmallInteger('hours')->default(4);
            $table->string('provider', 64)->nullable();    // 内训 / 外部机构
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('training_courses')->cascadeOnDelete();
            $table->string('session_no', 32)->unique();    // TS-2026-001
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('location', 64)->nullable();
            $table->string('trainer', 64)->nullable();
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->string('status', 16)->default('planned')->index(); // planned/ongoing/done
            $table->timestamps();
        });

        Schema::connection('hr')->create('training_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('result', 16)->default('pending')->index(); // pending/pass/fail
            $table->unsignedSmallInteger('score')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'employee_id']);
        });

        Schema::connection('hr')->create('skills', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();
            $table->string('name', 64);
            $table->string('category', 32)->default('操作技能');
            $table->unsignedTinyInteger('max_level')->default(5);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('employee_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->date('assessed_on')->nullable();
            $table->string('assessor', 64)->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'skill_id']);
        });

        Schema::connection('hr')->create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name', 96);                    // 电工证 / 叉车证 / 会计从业
            $table->string('cert_no', 64)->nullable();
            $table->string('issuer', 64)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expire_on')->nullable()->index();
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('shift_id')->constrained('shifts');
            $table->date('work_date')->index();
            $table->string('status', 16)->default('planned')->index(); // planned/actual/leave/absent
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date']);
        });
    }

    public function down(): void
    {
        foreach (['shift_assignments', 'certificates', 'employee_skills', 'skills', 'training_records', 'training_sessions', 'training_courses'] as $t) {
            Schema::connection('hr')->dropIfExists($t);
        }
    }
};
