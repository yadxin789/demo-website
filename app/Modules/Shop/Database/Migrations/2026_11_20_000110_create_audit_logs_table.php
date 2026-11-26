<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('operator_id')->nullable();
            $table->dateTime('acted_at');
            $table->string('module', 32); // auth/master/warehouse/purchase/sales/pos/finance/system
            $table->string('model', 100)->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('document_no', 50)->nullable();
            $table->string('action', 32); // create/update/delete/refund/price_change/discount/stock_move/approve/login/logout/export
            $table->string('field', 50)->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->string('source', 16)->default('web'); // web/pos/system
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->json('context')->nullable();
            $table->string('message', 255)->nullable();

            $table->index(['operator_id', 'acted_at'], 'audit_operator_idx');
            $table->index(['action', 'acted_at'], 'audit_action_idx');
            $table->index('document_no', 'audit_document_idx');
            $table->index(['module', 'acted_at'], 'audit_module_idx');

            $table->foreign('operator_id')->references('id')->on('users')->nullOnDelete();
        });

        // 数据库层拒绝修改 / 删除审计日志；仅 MySQL 建触发器（测试库 sqlite 跳过）
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER audit_logs_no_update BEFORE UPDATE ON audit_logs
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs 为只读审计日志，禁止修改';
                END;
            SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER audit_logs_no_delete BEFORE DELETE ON audit_logs
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs 为只读审计日志，禁止删除';
                END;
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_no_delete');
        }

        Schema::dropIfExists('audit_logs');
    }
};
