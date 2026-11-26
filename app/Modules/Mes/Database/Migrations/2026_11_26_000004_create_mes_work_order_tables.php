<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MES 工单（M0-05）：work_orders / work_order_operations / production_reports。
 *
 * 状态机（规划书 §2.3）：
 *   工单      draft → released → in_progress → completed → closed
 *   工单工序  pending → dispatched → in_progress → done
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        Schema::connection('mes')->create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_no', 32)->unique();                 // WO-xxxx
            $table->foreignId('product_material_id')->constrained('materials');
            $table->foreignId('bom_id')->nullable()->constrained('boms');
            $table->foreignId('routing_id')->nullable()->constrained('routings');
            $table->decimal('plan_qty', 14, 3);
            $table->decimal('done_qty', 14, 3)->default(0);
            $table->decimal('defect_qty', 14, 3)->default(0);
            $table->string('status', 16)->default('draft')->index();
            $table->date('plan_start')->nullable();
            $table->date('plan_end')->nullable();
            $table->unsignedTinyInteger('priority')->default(5);      // 1 最高
            $table->string('customer', 64)->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index(['status', 'plan_start']);
        });

        Schema::connection('mes')->create('work_order_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->unsignedSmallInteger('op_no');
            $table->string('op_name', 64);
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers');
            $table->decimal('plan_qty', 14, 3);
            $table->decimal('done_qty', 14, 3)->default(0);
            $table->decimal('defect_qty', 14, 3)->default(0);
            $table->string('status', 16)->default('pending')->index(); // pending/dispatched/in_progress/done
            $table->foreignId('assignee_id')->nullable()->constrained('users');
            $table->string('team', 64)->nullable();
            $table->decimal('std_minutes', 10, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index(['work_order_id', 'op_no']);
        });

        Schema::connection('mes')->create('production_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('operation_id')->nullable()->constrained('work_order_operations');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->foreignId('shift_id')->nullable()->constrained('shifts');
            $table->decimal('ok_qty', 14, 3)->default(0);
            $table->decimal('defect_qty', 14, 3)->default(0);
            $table->unsignedInteger('minutes')->default(0);
            $table->timestamp('reported_at')->useCurrent();
            $table->text('remark')->nullable();
            $table->timestamps();
            $table->index(['work_order_id', 'reported_at']);
        });
    }

    public function down(): void
    {
        foreach (['production_reports', 'work_order_operations', 'work_orders'] as $t) {
            Schema::connection('mes')->dropIfExists($t);
        }
    }
};
