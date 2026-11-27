<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MES 部门（S12-M03）。本产品独立表，连 mes 库（connection 由 BaseModel 强制）。
 */
class Department extends BaseModel
{
    protected $table = 'departments';

    protected $fillable = ['name', 'parent_id', 'sort', 'status'];

    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class, 'department_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

