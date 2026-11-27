<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MES 岗位（S12-M04）。本产品独立表，连 mes 库（connection 由 BaseModel 强制）。
 */
class Position extends BaseModel
{
    protected $table = 'positions';

    protected $fillable = ['name', 'department_id', 'status'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'position_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

