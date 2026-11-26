<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRun extends BaseModel
{
    public const CHANNEL_SCHEDULE = 'schedule';
    public const CHANNEL_QUEUE = 'queue';

    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'channel', 'name', 'status', 'exit_code', 'started_at', 'finished_at',
        'duration_ms', 'error', 'context',
    ];

    protected function casts(): array
    {
        return [
            'exit_code' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'context' => 'array',
        ];
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }
}
