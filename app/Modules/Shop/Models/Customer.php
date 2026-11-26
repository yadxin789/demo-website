<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends BaseModel
{
    protected $fillable = [
        'code', 'name', 'contact_name', 'phone', 'email', 'address',
        'tax_no', 'membership_level', 'credit_limit', 'default_discount_rate', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:4',
            'default_discount_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
