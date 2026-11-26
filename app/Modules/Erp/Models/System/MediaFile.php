<?php

namespace App\Modules\Erp\Models\System;

use App\Modules\Erp\Models\Concerns\RecordsDeletedBy;
use App\Modules\Erp\Models\Organization\User;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
class MediaFile extends \App\Modules\Erp\Models\BaseModel
{
    use SoftDeletes;
    use RecordsDeletedBy;

    public $timestamps = false;

    protected $fillable = [
        'file_name', 'file_path', 'file_size', 'mime_type',
        'category', 'uploader_id', 'ref_type', 'ref_id', 'created_at', 'deleted_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'created_at' => 'datetime',
    ];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /** 是否允许在线预览（图片 / Excel / PDF / Office），上传下载不受限 */
    public function canPreview(): bool
    {
        return app(\App\Modules\Erp\Services\FileService::class)->canPreview($this);
    }
}
