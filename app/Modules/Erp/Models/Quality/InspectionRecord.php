<?php

namespace App\Modules\Erp\Models\Quality;

use App\Modules\Erp\Models\Concerns\HasDataScope;
use App\Modules\Erp\Models\MasterData\Material;
use App\Modules\Erp\Models\Organization\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionRecord extends \App\Modules\Erp\Models\BaseModel
{
    use HasDataScope;

    /** 行级数据范围：本人 = 本记录检验员（S5） */
    protected static ?string $dataScopeOwnerColumn = 'inspector_id';

    protected $fillable = ['record_no', 'stage', 'material_id', 'ref_id', 'ref_type', 'sample_qty', 'qualified_qty', 'defect_qty', 'result', 'inspector_id', 'remark', 'inspected_at'];
    protected $casts = ['inspected_at' => 'datetime'];

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
