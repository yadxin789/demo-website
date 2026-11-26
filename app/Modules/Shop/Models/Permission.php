<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends BaseModel
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'module', 'type'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
