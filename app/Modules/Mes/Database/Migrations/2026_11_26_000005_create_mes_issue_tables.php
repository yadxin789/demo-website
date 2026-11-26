<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MES 物料领用（M0-06）：material_issues / material_issue_lines。
 *
 * 状态机：draft → issued（部分发料 partial）。
 * 单号 MC-02：MI-xxxx。
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        Schema::connection('mes')->create('material_issues', function (Blueprint $table) {
            $table->id();
            $table->string('issue_no', 32)->unique();                 // MI-xxxx
            $table->foreignId('work_order_id')->constrained('work_orders')->cascadeOnDelete();
            $table->string('status', 16)->default('draft')->index();  // draft/issued/partial
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users');
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::connection('mes')->create('material_issue_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('material_issue_id')->constrained('material_issues')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('materials');
            $table->decimal('required_qty', 14, 4)->default(0);
            $table->decimal('issued_qty', 14, 4)->default(0);
            $table->string('status', 16)->default('pending')->index(); // pending/issued/partial
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['material_issue_lines', 'material_issues'] as $t) {
            Schema::connection('mes')->dropIfExists($t);
        }
    }
};
