<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR 考勤（H3 域上半）：班次、打卡记录、考勤异常、加班。
 *
 * 判定口径：打卡记录 status 由 AttendanceService 按班次 + 容忍分钟算出
 * （normal/late/early/absent/missing），异常表只收"需要人处理"的那些。
 */
return new class extends Migration
{
    protected $connection = 'hr';

    public function up(): void
    {
        Schema::connection('hr')->create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 32);
            $table->string('work_start', 8)->default('09:00');
            $table->string('work_end', 8)->default('18:00');
            $table->unsignedSmallInteger('late_tolerance_min')->default(5);
            $table->boolean('cross_day')->default(false);
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
        });

        Schema::connection('hr')->create('attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->date('work_date')->index();
            $table->string('clock_in', 8)->nullable();
            $table->string('clock_out', 8)->nullable();
            $table->unsignedBigInteger('shift_id')->nullable()->index();
            $table->string('status', 16)->default('normal')->index();   // normal/late/early/absent/missing
            $table->integer('late_minutes')->default(0);
            $table->integer('early_minutes')->default(0);
            $table->string('remark', 255)->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'work_date'], 'hr_attendance_emp_date_unique');
        });

        Schema::connection('hr')->create('attendance_exceptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('attendance_id')->index();
            $table->unsignedBigInteger('employee_id')->index();
            $table->string('type', 16)->index();      // missing/late/early/absent
            $table->date('work_date')->index();
            $table->string('reason', 255)->nullable();
            $table->string('status', 16)->default('open')->index();   // open/handled
            $table->string('result', 32)->nullable();                 // 补卡/不计/扣款
            $table->unsignedBigInteger('handler_id')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('hr')->create('overtimes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id')->index();
            $table->date('date')->index();
            $table->decimal('hours', 4, 1)->default(0);
            $table->string('type', 16)->default('workday')->index();   // workday/weekend/holiday
            $table->string('reason', 255)->nullable();
            $table->string('status', 16)->default('pending')->index(); // pending/approved/rejected
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['overtimes', 'attendance_exceptions', 'attendance_records', 'shifts'] as $t) {
            Schema::connection('hr')->dropIfExists($t);
        }
    }
};
