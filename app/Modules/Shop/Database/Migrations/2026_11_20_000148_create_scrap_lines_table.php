<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::create('scrap_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('scrap_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('lot_no', 50)->nullable();
            $table->string('serial_no', 50)->nullable();
            $table->decimal('qty', 18, 4);
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('loss_amount', 18, 4)->default(0);
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['scrap_id'], 'scrap_line_scrap_idx');
            $table->foreign('scrap_id')->references('id')->on('scraps')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrap_lines');
    }
};
