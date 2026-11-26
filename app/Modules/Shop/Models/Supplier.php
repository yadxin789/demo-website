<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends BaseModel
{
    protected $fillable = ['code', 'name', 'contact_name', 'phone', 'email', 'address', 'tax_no', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
