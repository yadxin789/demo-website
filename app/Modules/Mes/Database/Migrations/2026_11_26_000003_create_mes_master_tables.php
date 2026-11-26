<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MES 主数据（M0-04）：物料 / 工作中心 / 班次 / BOM / 工艺路线，共 7 表。
 *
 * 建表顺序按外键依赖排列：materials → work_centers → shifts → boms → bom_lines → routings → routing_operations。
 * MC-02：物料编码统一 MJ- 前缀，与 ERP 的 MAT- 明确区分。
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        Schema::connection('mes')->create('materials', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();          // MJ-xxxx
            $table->string('name');
            $table->string('spec')->nullable();
            $table->string('unit', 16)->default('PCS');
            $table->string('category', 16)->index();       // raw / semi / finished
            $table->decimal('safety_stock', 14, 3)->default(0);
            $table->decimal('stock_qty', 14, 3)->default(0);
            $table->decimal('std_cost', 14, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('work_centers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('workshop', 64)->nullable();
            $table->unsignedInteger('daily_capacity')->default(480);   // 分钟/天
            $table->string('status', 16)->default('active');
            $table->timestamps();
        });

        Schema::connection('mes')->create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 32);
            $table->string('start_time', 5);    // 08:00
            $table->string('end_time', 5);      // 20:00
            $table->boolean('cross_day')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('mes')->create('boms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('product_material_id')->constrained('materials');
            $table->string('version', 16)->default('V1');
            $table->string('status', 16)->default('active')->index();
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('boms')->cascadeOnDelete();
            $table->foreignId('component_material_id')->constrained('materials');
            $table->decimal('qty_per', 14, 4)->default(1);
            $table->decimal('loss_rate', 6, 4)->default(0);   // 0.02 = 2%
            $table->unsignedSmallInteger('seq')->default(1);
            $table->timestamps();
            $table->index(['bom_id', 'seq']);
        });

        Schema::connection('mes')->create('routings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('product_material_id')->constrained('materials');
            $table->string('version', 16)->default('V1');
            $table->string('status', 16)->default('active')->index();
            $table->timestamps();
        });

        Schema::connection('mes')->create('routing_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_id')->constrained('routings')->cascadeOnDelete();
            $table->unsignedSmallInteger('op_no');            // 10 / 20 / 30
            $table->string('op_name', 64);
            $table->foreignId('work_center_id')->nullable()->constrained('work_centers');
            $table->decimal('std_minutes', 10, 2)->default(0);
            $table->boolean('inspection_required')->default(false);
            $table->timestamps();
            $table->index(['routing_id', 'op_no']);
        });
    }

    public function down(): void
    {
        foreach (['routing_operations', 'routings', 'bom_lines', 'boms', 'shifts', 'work_centers', 'materials'] as $t) {
            Schema::connection('mes')->dropIfExists($t);
        }
    }
};
