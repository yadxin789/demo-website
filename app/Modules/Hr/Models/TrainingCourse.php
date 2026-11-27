<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** 培训课程（HR，M7 增补，对标 OrangeHRM Training）。 */
class TrainingCourse extends BaseModel
{
    protected $fillable = ['code', 'title', 'category', 'hours', 'provider', 'is_active', 'description'];

    protected $casts = ['hours' => 'integer', 'is_active' => 'boolean'];

    public function sessions(): HasMany
    {
        return $this->hasMany(TrainingSession::class, 'course_id')->orderByDesc('start_date');
    }
}
