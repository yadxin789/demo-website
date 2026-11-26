<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MES 质量（M0-07）：inspection_templates / inspections / inspection_results / defects。
 *
 * 状态机：检验单 pending → passed / failed；不良 open → rework / scrap → closed。
 * 单号 MC-02：QC-xxxx。
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        Schema::connection('mes')->create('inspection_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('op_no')->nullable();   // 绑定某道工序
            $table->string('type', 8)->index();                  // IQC / IPQC / OQC
            $table->json('items');                               // [{name,std,min,max,unit}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('mes')->create('inspections', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_no', 32)->unique();       // QC-xxxx
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('operation_id')->nullable()->constrained('work_order_operations');
            $table->foreignId('template_id')->nullable()->constrained('inspection_templates');
            $table->string('type', 8)->index();
            $table->string('result', 16)->nullable()->index();   // null/pending → pass/fail
            $table->foreignId('inspector_id')->nullable()->constrained('users');
            $table->timestamp('inspected_at')->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->string('item_name', 64);
            $table->string('std_value', 64)->nullable();
            $table->string('actual_value', 64)->nullable();
            $table->string('verdict', 8)->default('ok');         // ok / ng
            $table->timestamps();
        });

        Schema::connection('mes')->create('defects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->foreignId('operation_id')->nullable()->constrained('work_order_operations');
            $table->string('defect_code', 32);
            $table->decimal('qty', 14, 3)->default(0);
            $table->string('disposal', 16)->nullable();          // rework / scrap
            $table->string('cause')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->string('status', 16)->default('open')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['defects', 'inspection_results', 'inspections', 'inspection_templates'] as $t) {
            Schema::connection('mes')->dropIfExists($t);
        }
    }
};
