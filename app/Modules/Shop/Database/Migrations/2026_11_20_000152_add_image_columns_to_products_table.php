<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'shop';

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('image_path')->nullable()->after('description'); // 原图相对路径（本地磁盘 products）
            $table->string('image_thumb_path')->nullable()->after('image_path'); // 缩略图相对路径
            $table->string('image_mime', 32)->nullable()->after('image_thumb_path'); // 原图 MIME
            $table->unsignedInteger('image_size')->nullable()->after('image_mime'); // 原图字节数
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['image_path', 'image_thumb_path', 'image_mime', 'image_size']);
        });
    }
};
