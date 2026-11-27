<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lot 批次（MES，M7 增补）。
 *
 * 追溯口径：成品的 lot 记 parent_lot_no（主要组成件的批次），
 * 于是可以顺着 parent 链往上查"这批成品用了哪批料"，
 * 也可以反向查"这批料被哪些成品批次用掉"。
 */
class Lot extends BaseModel
{
    protected $fillable = ['lot_no', 'material_id', 'source', 'work_order_id', 'qty', 'parent_lot_no', 'produced_at', 'remark'];

    protected $casts = ['qty' => 'decimal:3', 'produced_at' => 'date'];

    /** 路由按批次号绑定（/mes/trace/{lot_no}），比自增 id 直观，也方便现场扫批次号查 */
    public function getRouteKeyName(): string
    {
        return 'lot_no';
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_lot_no', 'lot_no');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_lot_no', 'lot_no');
    }
}
