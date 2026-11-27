<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 员工技能等级（1–5 级）。 */
class EmployeeSkill extends BaseModel
{
    protected $fillable = ['employee_id', 'skill_id', 'level', 'assessed_on', 'assessor', 'remark'];

    protected $casts = ['level' => 'integer', 'assessed_on' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function skill(): BelongsTo
    {
        return $this->belongsTo(Skill::class);
    }
}
