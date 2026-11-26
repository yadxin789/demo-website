<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        // 字段级差异「宽表」：由 audit_logs 的 field/old_value/new_value 派生而来，
        // 仅用于展示与报表，可随时由 `php artisan audit:backfill-diffs` 重建，不参与合规判定。
        Schema::create('audit_field_diffs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('log_id')->index();
            $table->string('model')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('action', 32)->nullable();
            $table->string('field', 64);
            $table->string('field_label', 64)->nullable();
            $table->text('old_text')->nullable();
            $table->text('new_text')->nullable();
            $table->string('change_type', 16)->default('changed'); // added 新增 / changed 修改 / removed 删除
            $table->timestamp('created_at')->nullable();

            $table->unique(['log_id', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_field_diffs');
    }
};
