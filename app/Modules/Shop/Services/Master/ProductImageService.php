<?php

namespace App\Modules\Shop\Services\Master;

use App\Modules\Shop\Exceptions\ImageException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 商品图片本地存储服务。
 *
 * 约定：
 * - 原图与缩略图全部写入本地磁盘 products（storage/app/products），不引用任何外部地址；
 * - 读取走应用路由读流输出，不依赖 public/storage 软链接，断网环境同样可见；
 * - 只接受 JPG / PNG / WebP，且以文件真实内容（getimagesize）判定类型，扩展名与 MIME 声明不作数。
 */
class ProductImageService
{
    public const DISK = 'products';

    /** 单张图片体积上限：5 MB */
    public const MAX_BYTES = 5242880;

    /** 原图最长边上限，避免超大会图吃满内存 */
    public const MAX_DIMENSION = 6000;

    /** 缩略图最长边 */
    public const THUMB_MAX = 240;

    /** IMAGETYPE => [扩展名, MIME] */
    private const TYPES = [
        IMAGETYPE_JPEG => ['ext' => 'jpg', 'mime' => 'image/jpeg'],
        IMAGETYPE_PNG => ['ext' => 'png', 'mime' => 'image/png'],
        IMAGETYPE_WEBP => ['ext' => 'webp', 'mime' => 'image/webp'],
    ];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * 校验上传文件并返回元信息；不合法时抛 ImageException（消息可直接展示）。
     *
     * @return array{ext: string, mime: string, width: int, height: int, size: int}
     */
    public function inspect(UploadedFile $file): array
    {
        if (! $file->isValid()) {
            throw new ImageException('图片上传失败，请重新选择文件');
        }

        $size = (int) $file->getSize();
        if ($size <= 0) {
            throw new ImageException('图片文件为空，请重新选择');
        }
        if ($size > self::MAX_BYTES) {
            throw new ImageException('图片不能超过 5 MB，请压缩后重新上传');
        }

        $info = @getimagesize($file->getRealPath());
        if ($info === false || ! isset(self::TYPES[$info[2]])) {
            throw new ImageException('只支持 JPG、PNG、WebP 格式的图片，请重新选择');
        }

        [$width, $height] = [(int) $info[0], (int) $info[1]];
        if ($width <= 0 || $height <= 0) {
            throw new ImageException('图片尺寸异常，请更换文件');
        }
        if (max($width, $height) > self::MAX_DIMENSION) {
            throw new ImageException('图片最长边不能超过 '.self::MAX_DIMENSION.' 像素，请压缩后重新上传');
        }

        $type = self::TYPES[$info[2]];

        return [
            'ext' => $type['ext'],
            'mime' => $type['mime'],
            'width' => $width,
            'height' => $height,
            'size' => $size,
        ];
    }

    /**
     * 保存商品主图：写原图 + 生成缩略图，成功后清理旧文件并记录审计。
     */
    public function store(Product $product, UploadedFile $file): Product
    {
        $info = $this->inspect($file);

        $disk = Storage::disk(self::DISK);
        $dir = 'products/'.now()->format('Ym');
        $base = Str::uuid()->toString();
        $originalPath = "{$dir}/{$base}.{$info['ext']}";
        $thumbPath = "{$dir}/{$base}_thumb.jpg";

        $disk->putFileAs($dir, $file, "{$base}.{$info['ext']}");
        if (! $disk->exists($originalPath)) {
            throw new ImageException('图片保存失败，请稍后重试');
        }

        try {
            $this->makeThumbnail($disk->path($originalPath), $disk->path($thumbPath));
        } catch (\Throwable $e) {
            $disk->delete($originalPath);

            throw $e instanceof ImageException ? $e : new ImageException('缩略图生成失败，图片可能已损坏');
        }

        $old = [
            'image_path' => $product->image_path,
            'image_thumb_path' => $product->image_thumb_path,
        ];

        $product->forceFill([
            'image_path' => $originalPath,
            'image_thumb_path' => $thumbPath,
            'image_mime' => $info['mime'],
            'image_size' => $info['size'],
        ])->save();

        $this->audit->record([
            'module' => 'master',
            'action' => AuditLog::ACTION_UPDATE,
            'model' => Product::class,
            'record_id' => $product->getKey(),
            'document_no' => $product->code,
            'field' => 'image_path',
            'old_value' => $old['image_path'],
            'new_value' => $originalPath,
            'message' => '更新商品主图',
            'context' => ['width' => $info['width'], 'height' => $info['height'], 'size' => $info['size']],
        ]);

        $this->deleteFiles($old);

        return $product;
    }

    /**
     * 删除商品主图与缩略图，并清空图片字段。
     */
    public function remove(Product $product): void
    {
        if (! $product->image_path && ! $product->image_thumb_path) {
            return;
        }

        $old = [
            'image_path' => $product->image_path,
            'image_thumb_path' => $product->image_thumb_path,
        ];

        $product->forceFill([
            'image_path' => null,
            'image_thumb_path' => null,
            'image_mime' => null,
            'image_size' => null,
        ])->save();

        $this->deleteFiles($old);

        $this->audit->record([
            'module' => 'master',
            'action' => AuditLog::ACTION_UPDATE,
            'model' => Product::class,
            'record_id' => $product->getKey(),
            'document_no' => $product->code,
            'field' => 'image_path',
            'old_value' => $old['image_path'],
            'new_value' => null,
            'message' => '移除商品主图',
        ]);
    }

    /**
     * 取某个变体（thumb 缩略图 / full 原图）在本地磁盘上的相对路径；不存在返回 null。
     */
    public function path(Product $product, string $variant = 'thumb'): ?string
    {
        $column = $variant === 'full' ? 'image_path' : 'image_thumb_path';
        $value = $product->{$column};

        if (! $value) {
            return null;
        }

        return Storage::disk(self::DISK)->exists($value) ? $value : null;
    }

    /**
     * 取浏览地址（走应用路由，不暴露磁盘路径）。
     */
    public function url(?Product $product, string $variant = 'thumb'): ?string
    {
        if (! $product || ! $product->image_path) {
            return null;
        }

        return route('shop.products.image', ['product' => $product->getKey(), 'variant' => $variant]);
    }

    /**
     * 原图 MIME；缺省回退为 jpg，仅供响应头使用。
     */
    public function mime(Product $product, string $variant = 'thumb'): string
    {
        if ($variant !== 'full') {
            return 'image/jpeg';
        }

        return $product->image_mime ?: 'application/octet-stream';
    }

    /**
     * 生成等比缩略图（JPEG，透明底铺白，避免 PNG 透明通道变黑）。
     */
    private function makeThumbnail(string $source, string $target): void
    {
        $raw = @file_get_contents($source);
        $image = $raw === false ? false : @imagecreatefromstring($raw);
        if ($image === false) {
            throw new ImageException('无法解析图片内容，请更换文件');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1.0, self::THUMB_MAX / max($width, $height, 1));
        $thumbWidth = max(1, (int) round($width * $scale));
        $thumbHeight = max(1, (int) round($height * $scale));

        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);
        $white = imagecolorallocate($thumb, 255, 255, 255);
        imagefilledrectangle($thumb, 0, 0, $thumbWidth, $thumbHeight, $white);
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);

        $written = imagejpeg($thumb, $target, 82);

        imagedestroy($thumb);
        imagedestroy($image);

        if (! $written || ! is_file($target) || filesize($target) === 0) {
            @unlink($target);

            throw new ImageException('缩略图生成失败，请更换图片后重试');
        }
    }

    /**
     * 清理磁盘上的旧文件（原图/缩略图），文件不存在时静默跳过。
     *
     * @param  array{image_path?: ?string, image_thumb_path?: ?string}  $paths
     */
    private function deleteFiles(array $paths): void
    {
        $disk = Storage::disk(self::DISK);

        foreach ([$paths['image_path'] ?? null, $paths['image_thumb_path'] ?? null] as $path) {
            if (is_string($path) && $path !== '' && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
