<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR 招聘与绩效（H5 域）。
 *
 * HC-M02：招聘需求 REQ-2026-001。
 * 绩效口径（演示版）：总分 = Σ(上级评分 × 权重)，权重按周期内指标合计 100%。
 */
return new class extends Migration
{
    protected $connection = 'hr';

    public function up(): void
    {
        Schema::connection('hr')->create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('req_no', 32)->unique();
            $table->unsignedBigInteger('position_id')->index();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->integer('headcount')->default(1);
            $table->integer('hired')->default(0);
            $table->string('urgency', 16)->default('normal')->index();   // high/normal
            $table->string('status', 16)->default('open')->index();      // open/closed/cancelled
            $table->unsignedBigInteger('requester_id')->nullable();
            $table->string('remark', 255)->nullable();
            $table->date('raised_on')->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requisition_id')->index();
            $table->string('name', 64)->index();
            $table->string('phone', 32)->nullable();
            $table->string('email', 96)->nullable();
            $table->string('source', 32)->nullable();       // 内推/猎头/BOSS/校招
            $table->unsignedTinyInteger('years')->default(0);
            $table->string('resume_summary', 500)->nullable();
            $table->string('stage', 16)->default('screening')->index();  // screening/interview/offer/onboard/reject
            $table->string('remark', 255)->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('interviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('candidate_id')->index();
            $table->unsignedTinyInteger('round')->default(1);
            $table->unsignedBigInteger('interviewer_id')->nullable()->index();
            $table->unsignedTinyInteger('score')->nullable();   // 1–10
            $table->string('result', 16)->nullable()->index();  // pass/fail/hold
            $table->string('feedback', 500)->nullable();
            $table->timestamp('interviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('period', 32)->index();     // 2026Q4
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 16)->default('draft')->index();   // draft/in_progress/closed
            $table->timestamps();
        });

        Schema::connection('hr')->create('performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cycle_id')->index();
            $table->string('name', 64);
            $table->decimal('weight', 5, 2)->default(20);   // 权重 %
            $table->string('target', 96)->nullable();      // 目标描述
            $table->string('scope', 32)->nullable();       // 通用 / 部门 / 岗位
            $table->timestamps();
        });

        Schema::connection('hr')->create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cycle_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedBigInteger('indicator_id')->index();
            $table->unsignedTinyInteger('self_score')->nullable();      // 0–100
            $table->unsignedTinyInteger('manager_score')->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->string('comment', 255)->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'employee_id', 'indicator_id'], 'hr_perf_review_unique');
        });
    }

    public function down(): void
    {
        foreach (['performance_reviews', 'performance_indicators', 'performance_cycles', 'interviews', 'candidates', 'requisitions'] as $t) {
            Schema::connection('hr')->dropIfExists($t);
        }
    }
};
