<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 培训场次（一期班）。 */
class TrainingSession extends BaseModel
{
    protected $fillable = ['course_id', 'session_no', 'start_date', 'end_date', 'location', 'trainer', 'capacity', 'status'];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'capacity' => 'integer'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(TrainingCourse::class, 'course_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(TrainingRecord::class, 'session_id');
    }

    /** 已通过的记录数 */
    public function getPassedCountAttribute(): int
    {
        return $this->records->where('result', 'pass')->count();
    }
}
