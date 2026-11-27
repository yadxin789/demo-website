<?php

declare(strict_types=1);

namespace App\Modules\Hr\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 证件与资质（含到期提醒）。 */
class Certificate extends BaseModel
{
    protected $fillable = ['employee_id', 'name', 'cert_no', 'issuer', 'issued_on', 'expire_on', 'remark'];

    protected $casts = ['issued_on' => 'date', 'expire_on' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** 距到期天数（已过期返回负数） */
    public function getDaysToExpireAttribute(): ?int
    {
        return $this->expire_on ? (int) now()->startOfDay()->diffInDays($this->expire_on, false) : null;
    }
}
