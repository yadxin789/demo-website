<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR 员工与异动（H2 域）：员工档案、合同、异动流水。
 *
 * HC-M02：工号 HR2026xxx。
 * HC-M05：身份证脱敏存储（id_card_masked），不存明文。
 */
return new class extends Migration
{
    protected $connection = 'hr';

    public function up(): void
    {
        Schema::connection('hr')->create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no', 32)->unique();
            $table->string('name', 64)->index();
            $table->string('gender', 8)->nullable();
            $table->date('birthday')->nullable();
            $table->string('id_card_masked', 32)->nullable();   // 3201**********1234
            $table->string('phone', 32)->nullable();
            $table->string('email', 96)->nullable();
            $table->unsignedBigInteger('department_id')->nullable()->index();
            $table->unsignedBigInteger('position_id')->nullable()->index();
            $table->date('hire_date')->index();
            $table->date('regular_date')->nullable();
            $table->date('leave_date')->nullable();
            $table->string('status', 16)->default('probation')->index();  // probation/active/left
            $table->string('education', 32)->nullable();
            $table->string('remark', 255)->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('employee_contracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('contract_no', 32)->unique();
            $table->string('type', 16)->default('fixed');      // fixed/open
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('sign_date')->nullable();
            $table->string('status', 16)->default('active')->index();   // active/expired/renewed
            $table->string('remark', 255)->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('employee_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('type', 16)->index();   // hire/regular/transfer/promote/leave
            $table->date('effective_date')->index();
            $table->string('from_dept', 64)->nullable();
            $table->string('to_dept', 64)->nullable();
            $table->string('from_position', 64)->nullable();
            $table->string('to_position', 64)->nullable();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->string('remark', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['employee_movements', 'employee_contracts', 'employees'] as $t) {
            Schema::connection('hr')->dropIfExists($t);
        }
    }
};
