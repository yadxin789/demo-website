<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends BaseModel
{
    protected $fillable = ['name', 'code', 'type', 'account_id', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'account_id' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
