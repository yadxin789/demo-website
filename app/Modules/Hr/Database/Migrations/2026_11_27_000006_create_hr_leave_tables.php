<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR 请假与假期余额（H3 域下半）。
 *
 * HC-M02：请假单号 LV-2026-0001。
 * 余额口径：annual 年假 / compensatory 调休，审批通过时由 LeaveService 扣减。
 */
return new class extends Migration
{
    protected $connection = 'hr';

    public function up(): void
    {
        Schema::connection('hr')->create('leaves', function (Blueprint $table) {
            $table->id();
            $table->string('leave_no', 32)->unique();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('type', 16)->index();        // annual/sick/personal/compensatory
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 4, 1)->default(1);
            $table->string('reason', 255)->nullable();
            $table->string('status', 16)->default('pending')->index();  // pending/approved/rejected
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('reject_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->unsignedSmallInteger('year')->index();
            $table->string('type', 16)->default('annual')->index();
            $table->decimal('total', 5, 1)->default(0);
            $table->decimal('used', 5, 1)->default(0);
            $table->decimal('remaining', 5, 1)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'year', 'type'], 'hr_leave_balance_unique');
        });
    }

    public function down(): void
    {
        foreach (['leave_balances', 'leaves'] as $t) {
            Schema::connection('hr')->dropIfExists($t);
        }
    }
};
