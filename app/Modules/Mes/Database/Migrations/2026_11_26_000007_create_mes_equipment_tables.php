<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MES 设备与排产（M0-08）：equipments / equipment_checks / equipment_repairs / aps_schedules。
 *
 * 设备状态：running / idle / repairing / scrapped。
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        Schema::connection('mes')->create('equipments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers');
            $table->string('status', 16)->default('idle')->index();
            $table->unsignedInteger('check_cycle_days')->default(30);
            $table->timestamp('last_check_at')->nullable();
            $table->date('purchased_on')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('equipment_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->timestamp('checked_at')->useCurrent();
            $table->foreignId('checker_id')->nullable()->constrained('users');
            $table->string('result', 16)->default('ok');          // ok / ng
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('equipment_repairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->text('fault_desc');
            $table->foreignId('reported_by')->nullable()->constrained('users');
            $table->foreignId('handler_id')->nullable()->constrained('users');
            $table->unsignedInteger('down_minutes')->default(0);
            $table->string('status', 16)->default('open')->index(); // open/handling/fixed/closed
            $table->timestamp('fixed_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('aps_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('operation_id')->nullable()->constrained('work_order_operations');
            $table->foreignId('work_center_id')->constrained('work_centers');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->unsignedSmallInteger('seq')->default(1);
            $table->timestamps();
            $table->index(['work_center_id', 'start_at']);
        });
    }

    public function down(): void
    {
        foreach (['aps_schedules', 'equipment_repairs', 'equipment_checks', 'equipments'] as $t) {
            Schema::connection('mes')->dropIfExists($t);
        }
    }
};
