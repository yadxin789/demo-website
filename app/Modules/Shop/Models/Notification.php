<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Route;

class Notification extends BaseModel
{
    public const TYPE_SYSTEM = 'system';
    public const TYPE_STOCK = 'stock';
    public const TYPE_LOT = 'lot';
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_FINANCE = 'finance';
    public const TYPE_AI = 'ai';

    public const LEVEL_INFO = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_DANGER = 'danger';

    protected $fillable = [
        'user_id', 'type', 'level', 'title', 'content',
        'action_route', 'dedupe_key', 'is_read', 'read_at',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead(): void
    {
        if ($this->is_read) {
            return;
        }

        $this->forceFill(['is_read' => true, 'read_at' => now()])->save();
    }

    /**
     * 跳转地址只保存路由名，换域名/端口后依然可用。
     */
    public function actionUrl(): ?string
    {
        if (blank($this->action_route) || ! Route::has($this->action_route)) {
            return null;
        }

        return route($this->action_route);
    }

    public function icon(): string
    {
        return match ($this->type) {
            self::TYPE_STOCK => 'fa-boxes-stacked',
            self::TYPE_LOT => 'fa-hourglass-half',
            self::TYPE_PURCHASE => 'fa-dolly',
            self::TYPE_FINANCE => 'fa-hand-holding-dollar',
            self::TYPE_AI => 'fa-robot',
            default => 'fa-circle-info',
        };
    }

    public function levelLabel(): string
    {
        return match ($this->level) {
            self::LEVEL_DANGER => '严重',
            self::LEVEL_WARNING => '预警',
            default => '提示',
        };
    }
}
