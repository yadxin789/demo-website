<?php

namespace App\Modules\Erp\Models\Production;

use App\Modules\Erp\Models\MasterData\Material;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StocktakeLine extends \App\Modules\Erp\Models\BaseModel
{
    public $timestamps = false;
    protected $fillable = ['stocktake_id', 'material_id', 'lot_no', 'book_qty', 'actual_qty', 'diff_qty'];
    protected $casts = ['book_qty' => 'decimal:2', 'actual_qty' => 'decimal:2', 'diff_qty' => 'decimal:2'];

    public function stocktake(): BelongsTo
    {
        return $this->belongsTo(Stocktake::class, 'stocktake_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
