<?php

namespace App\Modules\Erp\Models\Office;

use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['title', 'content', 'category', 'publisher_id', 'published_at', 'status'];

    protected $casts = [
        'published_at' => 'datetime',
        'status' => 'integer',
    ];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }
}
