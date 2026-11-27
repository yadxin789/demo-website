<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 个人培训记录（报名 + 结果）。 */
class TrainingRecord extends BaseModel
{
    protected $fillable = ['session_id', 'employee_id', 'result', 'score', 'completed_at', 'remark'];

    protected $casts = ['score' => 'integer', 'completed_at' => 'datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TrainingSession::class, 'session_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
