<?php

namespace App\Modules\Erp\Models\Sales;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Modules\Erp\Models\MasterData\Customer;
use App\Modules\Erp\Models\Organization\User;

class SalesQuote extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = [
        'quote_no', 'customer_id', 'quote_date', 'valid_until', 'status',
        'total_amount', 'remark', 'created_by',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'valid_until' => 'date',
        'total_amount' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SalesQuoteLine::class, 'quote_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
