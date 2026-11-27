<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 门户线索表（S7-12）
 *
 * 只落在 portal.sqlite 里，只存"谁想约演示"这一件事。
 * HC-14：门户不读任何产品库；本表也不被任何产品读写。
 */
return new class extends Migration
{
    public $connection = 'portal';

    public function up(): void
    {
        Schema::connection('portal')->create('leads', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 40);
            $table->string('contact', 60);
            $table->string('company', 80)->nullable();
            $table->string('interest', 20)->nullable();
            $table->string('message', 500)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('ua', 200)->nullable();
            $table->string('status', 20)->default('new');
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('portal')->dropIfExists('leads');
    }
};
