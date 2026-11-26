<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MaterialIssueLine 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class MaterialIssueLine extends BaseModel
{
    protected $fillable = ['material_issue_id', 'material_id', 'required_qty', 'issued_qty', 'status'];

    protected $casts = ['required_qty' => 'decimal:4', 'issued_qty' => 'decimal:4'];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(MaterialIssue::class, 'material_issue_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}