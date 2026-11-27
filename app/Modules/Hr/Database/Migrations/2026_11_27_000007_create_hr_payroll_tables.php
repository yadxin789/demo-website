<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR 薪资（H4 域）：岗位薪资结构、工资批次、工资明细。
 *
 * HC-M02：批次号 PAY-202611。
 * 口径（演示版简化）：应发 = 基本 + 岗位 + 绩效 + 补贴 + 加班费；
 *                实发 = 应发 − 请假扣款 − 社保 − 个税。
 */
return new class extends Migration
{
    protected $connection = 'hr';

    public function up(): void
    {
        Schema::connection('hr')->create('salary_structures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('position_id')->index();
            $table->decimal('base', 10, 2)->default(0);
            $table->decimal('post', 10, 2)->default(0);
            $table->decimal('performance', 10, 2)->default(0);
            $table->decimal('allowance', 10, 2)->default(0);
            $table->date('effective_date')->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('period', 7)->unique();          // 2026-11
            $table->string('payroll_no', 32)->unique();     // PAY-202611
            $table->string('status', 16)->default('draft')->index();   // draft/confirmed/paid
            $table->integer('employee_count')->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('employee_no', 32)->index();
            $table->string('employee_name', 64);
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->decimal('base', 10, 2)->default(0);
            $table->decimal('post', 10, 2)->default(0);
            $table->decimal('performance', 10, 2)->default(0);
            $table->decimal('allowance', 10, 2)->default(0);
            $table->decimal('overtime_pay', 10, 2)->default(0);
            $table->decimal('leave_deduct', 10, 2)->default(0);
            $table->decimal('social_insurance', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('gross', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->timestamps();
            $table->unique(['run_id', 'employee_id'], 'hr_payroll_item_unique');
        });
    }

    public function down(): void
    {
        foreach (['payroll_items', 'payroll_runs', 'salary_structures'] as $t) {
            Schema::connection('hr')->dropIfExists($t);
        }
    }
};
