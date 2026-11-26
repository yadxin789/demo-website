<?php

namespace App\Modules\Erp\Models\Ai;


class AiSkill extends \App\Modules\Erp\Models\BaseModel
{
    protected $table = 'ai_skills';

    protected $fillable = ['code', 'name', 'description', 'type', 'config', 'enabled'];

    protected $casts = [
        'config' => 'array',
        'enabled' => 'boolean',
    ];
}
