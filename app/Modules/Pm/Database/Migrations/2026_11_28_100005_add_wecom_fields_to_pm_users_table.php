<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 补充 users 表的企业微信绑定/通知字段（与参考目录 pm-server-openEuler 对齐）。
     */
    public function up(): void
    {
        $columns = [
            'wecom_userid' => fn (Blueprint $t) => $t->string('wecom_userid')->nullable()->index(),
            'wecom_bound_at' => fn (Blueprint $t) => $t->timestamp('wecom_bound_at')->nullable(),
            'wecom_notify_enabled' => fn (Blueprint $t) => $t->boolean('wecom_notify_enabled')->default(true),
            'wecom_notify_settings' => fn (Blueprint $t) => $t->text('wecom_notify_settings')->nullable(),
        ];

        foreach ($columns as $name => $adder) {
            if (! Schema::hasColumn('users', $name)) {
                Schema::table('users', function (Blueprint $table) use ($adder) {
                    $adder($table);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['wecom_userid', 'wecom_bound_at', 'wecom_notify_enabled', 'wecom_notify_settings'] as $name) {
            if (Schema::hasColumn('users', $name)) {
                Schema::table('users', function (Blueprint $table) use ($name) {
                    $table->dropColumn($name);
                });
            }
        }
    }
};
