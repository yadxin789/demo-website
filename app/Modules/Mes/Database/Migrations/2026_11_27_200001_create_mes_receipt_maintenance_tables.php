<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MES 完工入库 / 批次追溯 / 设备保养（M7 增补）。
 *
 * 对标 Odoo Manufacturing（Manufacturing Order → Produce → Finished goods receipt）
 * 与 ERPNext Manufacturing（Job Card 完工 → Stock Entry），补齐"报工之后"的闭环：
 *   报工 → 完工入库（finished_receipts，带批次号）→ 关单，以及批次上下游追溯（lots）。
 * 设备侧对标 Odoo Maintenance：保养计划（maintenance_plans）+ 保养/维修工单（maintenance_orders）。
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        Schema::connection('mes')->create('finished_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 32)->unique();                 // FR-2026-0001
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials');
            $table->string('lot_no', 32)->index();                       // 完工批次号
            $table->decimal('qty', 14, 3);
            $table->string('warehouse', 32)->default('成品库');
            $table->foreignId('received_by')->nullable()->constrained('users');
            $table->timestamp('received_at')->useCurrent();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index(['work_order_id', 'received_at']);
        });

        Schema::connection('mes')->create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_no', 32)->unique();
            $table->foreignId('material_id')->constrained('materials');
            $table->string('source', 16)->default('production')->index(); // production/purchase/other
            $table->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $table->decimal('qty', 14, 3)->default(0);
            $table->string('parent_lot_no', 32)->nullable()->index();     // 上游批次（组成来源）
            $table->date('produced_at')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 24)->unique();                        // MP-001
            $table->string('name', 64);
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->unsignedSmallInteger('cycle_days')->default(30);      // 保养周期（天）
            $table->unsignedSmallInteger('standard_minutes')->default(60);
            $table->date('last_done_at')->nullable();
            $table->date('next_due_at')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('maintenance_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 24)->unique();                    // MO-2026-0001
            $table->foreignId('plan_id')->nullable()->constrained('maintenance_plans')->nullOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments');
            $table->string('type', 8)->default('pm');                    // pm 保养 / cm 维修
            $table->string('status', 16)->default('pending')->index();    // pending/in_progress/done
            $table->date('due_at')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users');
            $table->decimal('cost_hours', 6, 2)->default(0);
            $table->text('remark')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['maintenance_orders', 'maintenance_plans', 'lots', 'finished_receipts'] as $t) {
            Schema::connection('mes')->dropIfExists($t);
        }
    }
};
