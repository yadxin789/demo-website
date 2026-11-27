<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** 技能项（技能字典）。 */
class Skill extends BaseModel
{
    protected $fillable = ['code', 'name', 'category', 'max_level', 'description'];

    protected $casts = ['max_level' => 'integer'];

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class, 'skill_id');
    }
}
