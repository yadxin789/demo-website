<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

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
