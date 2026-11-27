<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MES 工艺文件（SOP）下发（M7 增补 2）。
 *
 * 对标 Odoo Manufacturing 的 "Work Instructions"、ERPNext 的 "Operation → Job Card 附件"：
 * 每道工序可挂一份作业指导书，记录版本、生效日期、正文与文件，现场按工序查阅。
 */
return new class extends Migration
{
    protected $connection = 'mes';

    public function up(): void
    {
        Schema::connection('mes')->create('sop_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('routing_operation_id')->constrained('routing_operations')->cascadeOnDelete();
            $table->string('code', 32)->unique();                        // SOP-{工艺路线}-{工序号}
            $table->string('title', 120);
            $table->string('version', 20)->default('A/0');
            $table->text('content')->nullable();                         // 正文要点（无文件时也能看）
            $table->string('file_path', 191)->nullable();                // storage/app/sop/xxx
            $table->string('file_name', 191)->nullable();
            $table->string('status', 16)->default('active');             // active / draft / obsolete
            $table->date('effective_on')->nullable();
            $table->string('author', 64)->nullable();
            $table->timestamps();

            $table->index(['routing_operation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('mes')->dropIfExists('sop_documents');
    }
};
