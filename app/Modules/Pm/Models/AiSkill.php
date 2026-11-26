<?php

namespace App\Modules\Pm\Models;


class AiSkill extends BaseModel
{
    protected $table = 'ai_skills';

    protected $fillable = [
        'name', 'slug', 'description', 'type', 'config', 'enabled',
    ];

    protected $casts = [
        'config' => 'array',
        'enabled' => 'boolean',
    ];
}
