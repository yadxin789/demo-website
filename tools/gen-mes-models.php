<?php

declare(strict_types=1);

/*
 * gen-mes-models.php —— 一次性生成 MES 的 20 个业务模型（M0-09）
 *
 * 只生成一次，生成后可删除；重跑会覆盖同名文件。
 * 约束：全部 extends BaseModel（$connection = 'mes'），不出现裸 extends Model（G-02）。
 */

$root = dirname(__DIR__);
$dir = $root . '/app/Modules/Mes/Models';
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

$models = [
    'Material' => [
        'fillable' => ['code', 'name', 'spec', 'unit', 'category', 'safety_stock', 'stock_qty', 'std_cost', 'is_active', 'remark'],
        'casts' => ['safety_stock' => 'decimal:3', 'stock_qty' => 'decimal:3', 'std_cost' => 'decimal:4', 'is_active' => 'bool'],
        'relations' => <<<'PHP'
    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class, 'product_material_id');
    }

    public function routings(): HasMany
    {
        return $this->hasMany(Routing::class, 'product_material_id');
    }
PHP,
    ],
    'Bom' => [
        'fillable' => ['code', 'product_material_id', 'version', 'status', 'remark'],
        'casts' => [],
        'relations' => <<<'PHP'
    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BomLine::class)->orderBy('seq');
    }
PHP,
    ],
    'BomLine' => [
        'fillable' => ['bom_id', 'component_material_id', 'qty_per', 'loss_rate', 'seq'],
        'casts' => ['qty_per' => 'decimal:4', 'loss_rate' => 'decimal:4'],
        'relations' => <<<'PHP'
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'component_material_id');
    }
PHP,
    ],
    'Routing' => [
        'fillable' => ['code', 'product_material_id', 'version', 'status'],
        'casts' => [],
        'relations' => <<<'PHP'
    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class)->orderBy('op_no');
    }
PHP,
    ],
    'RoutingOperation' => [
        'fillable' => ['routing_id', 'op_no', 'op_name', 'work_center_id', 'std_minutes', 'inspection_required'],
        'casts' => ['std_minutes' => 'decimal:2', 'inspection_required' => 'bool'],
        'relations' => <<<'PHP'
    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }
PHP,
    ],
    'WorkCenter' => [
        'fillable' => ['code', 'name', 'workshop', 'daily_capacity', 'status'],
        'casts' => ['daily_capacity' => 'integer'],
        'relations' => <<<'PHP'
    public function operations(): HasMany
    {
        return $this->hasMany(WorkOrderOperation::class);
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class);
    }
PHP,
    ],
    'Shift' => [
        'fillable' => ['code', 'name', 'start_time', 'end_time', 'cross_day', 'is_active'],
        'casts' => ['cross_day' => 'bool', 'is_active' => 'bool'],
        'relations' => '',
    ],
    'WorkOrder' => [
        'fillable' => ['order_no', 'product_material_id', 'bom_id', 'routing_id', 'plan_qty', 'done_qty', 'defect_qty', 'status', 'plan_start', 'plan_end', 'priority', 'customer', 'remark'],
        'casts' => ['plan_qty' => 'decimal:3', 'done_qty' => 'decimal:3', 'defect_qty' => 'decimal:3', 'plan_start' => 'date', 'plan_end' => 'date', 'priority' => 'integer'],
        'relations' => <<<'PHP'
    public function product(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'product_material_id');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(WorkOrderOperation::class)->orderBy('op_no');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProductionReport::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(MaterialIssue::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function defects(): HasMany
    {
        return $this->hasMany(Defect::class);
    }

    /** 完成率（0–100，整数） */
    public function getProgressAttribute(): int
    {
        return $this->plan_qty > 0 ? (int) round((float) $this->done_qty / (float) $this->plan_qty * 100) : 0;
    }
PHP,
    ],
    'WorkOrderOperation' => [
        'fillable' => ['work_order_id', 'op_no', 'op_name', 'work_center_id', 'plan_qty', 'done_qty', 'defect_qty', 'status', 'assignee_id', 'team', 'std_minutes', 'started_at', 'finished_at'],
        'casts' => ['plan_qty' => 'decimal:3', 'done_qty' => 'decimal:3', 'defect_qty' => 'decimal:3', 'std_minutes' => 'decimal:2', 'started_at' => 'datetime', 'finished_at' => 'datetime'],
        'relations' => <<<'PHP'
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProductionReport::class, 'operation_id');
    }
PHP,
    ],
    'ProductionReport' => [
        'fillable' => ['work_order_id', 'operation_id', 'user_id', 'shift_id', 'ok_qty', 'defect_qty', 'minutes', 'reported_at', 'remark'],
        'casts' => ['ok_qty' => 'decimal:3', 'defect_qty' => 'decimal:3', 'minutes' => 'integer', 'reported_at' => 'datetime'],
        'relations' => <<<'PHP'
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(WorkOrderOperation::class, 'operation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
PHP,
    ],
    'MaterialIssue' => [
        'fillable' => ['issue_no', 'work_order_id', 'status', 'issued_at', 'issued_by', 'remark'],
        'casts' => ['issued_at' => 'datetime'],
        'relations' => <<<'PHP'
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialIssueLine::class);
    }
PHP,
    ],
    'MaterialIssueLine' => [
        'fillable' => ['material_issue_id', 'material_id', 'required_qty', 'issued_qty', 'status'],
        'casts' => ['required_qty' => 'decimal:4', 'issued_qty' => 'decimal:4'],
        'relations' => <<<'PHP'
    public function issue(): BelongsTo
    {
        return $this->belongsTo(MaterialIssue::class, 'material_issue_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
PHP,
    ],
    'InspectionTemplate' => [
        'fillable' => ['code', 'name', 'op_no', 'type', 'items', 'is_active'],
        'casts' => ['items' => 'array', 'is_active' => 'bool', 'op_no' => 'integer'],
        'relations' => '',
    ],
    'Inspection' => [
        'fillable' => ['inspection_no', 'work_order_id', 'operation_id', 'template_id', 'type', 'result', 'inspector_id', 'inspected_at', 'remark'],
        'casts' => ['inspected_at' => 'datetime'],
        'relations' => <<<'PHP'
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(WorkOrderOperation::class, 'operation_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'template_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(InspectionResult::class);
    }
PHP,
    ],
    'InspectionResult' => [
        'fillable' => ['inspection_id', 'item_name', 'std_value', 'actual_value', 'verdict'],
        'casts' => [],
        'relations' => <<<'PHP'
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
PHP,
    ],
    'Defect' => [
        'fillable' => ['work_order_id', 'operation_id', 'defect_code', 'qty', 'disposal', 'cause', 'owner_id', 'status'],
        'casts' => ['qty' => 'decimal:3'],
        'relations' => <<<'PHP'
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(WorkOrderOperation::class, 'operation_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
PHP,
    ],
    'Equipment' => [
        'fillable' => ['code', 'name', 'work_center_id', 'status', 'check_cycle_days', 'last_check_at', 'purchased_on', 'remark'],
        'casts' => ['last_check_at' => 'datetime', 'purchased_on' => 'date', 'check_cycle_days' => 'integer'],
        'relations' => <<<'PHP'
    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(EquipmentCheck::class);
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(EquipmentRepair::class);
    }
PHP,
    ],
    'EquipmentCheck' => [
        'fillable' => ['equipment_id', 'checked_at', 'checker_id', 'result', 'remark'],
        'casts' => ['checked_at' => 'datetime'],
        'relations' => <<<'PHP'
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function checker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checker_id');
    }
PHP,
    ],
    'EquipmentRepair' => [
        'fillable' => ['equipment_id', 'fault_desc', 'reported_by', 'handler_id', 'down_minutes', 'status', 'fixed_at'],
        'casts' => ['fixed_at' => 'datetime', 'down_minutes' => 'integer'],
        'relations' => <<<'PHP'
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_id');
    }
PHP,
    ],
    'ApsSchedule' => [
        'fillable' => ['work_order_id', 'operation_id', 'work_center_id', 'start_at', 'end_at', 'seq'],
        'casts' => ['start_at' => 'datetime', 'end_at' => 'datetime', 'seq' => 'integer'],
        'relations' => <<<'PHP'
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(WorkOrderOperation::class, 'operation_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }
PHP,
    ],
];

$tpl = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CLASSNAME 模型（MES）。
 *
 * extends BaseModel：$connection = 'mes'，表名列名都不带产品前缀（MC-01）。
 * 本类不引用 ERP / PM / Shop 的任何类或表（MC-03）。
 */
class CLASSNAME extends BaseModel
{
    protected $fillable = [FILLABLE];

    protected $casts = CASTS;
RELATIONS}
PHP;

$written = 0;
foreach ($models as $name => $def) {
    $fillable = "'" . implode("', '", $def['fillable']) . "'";

    if ($def['casts'] === []) {
        $casts = '[]';
    } else {
        $pairs = [];
        foreach ($def['casts'] as $k => $v) {
            $pairs[] = "'{$k}' => '{$v}'";
        }
        $casts = '[' . implode(', ', $pairs) . ']';
    }

    $relations = $def['relations'] !== '' ? "\n" . $def['relations'] . "\n" : '';

    $code = str_replace(
        ['CLASSNAME', 'FILLABLE', 'CASTS', 'RELATIONS'],
        [$name, $fillable, $casts, $relations],
        $tpl
    );

    // 无关联时去掉多余的 use，避免 php -l 之外的静态检查噪音
    if ($def['relations'] === '') {
        $code = str_replace("use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;\nuse Illuminate\\Database\\Eloquent\\Relations\\HasMany;\n\n", '', $code);
    }

    file_put_contents("{$dir}/{$name}.php", $code);
    $written++;
    echo "  + Models/{$name}.php\n";
}

// User 模型补 HasRoles（spatie），否则 Guard 认证后无法分配角色
$userPath = "{$dir}/User.php";
if (is_file($userPath)) {
    $u = file_get_contents($userPath);
    if (! str_contains($u, 'HasRoles')) {
        $u = preg_replace(
            '/^use .*Authenticatable;$/m',
            "use Illuminate\\Foundation\\Auth\\User as Authenticatable;\nuse Spatie\\Permission\\Traits\\HasRoles;",
            $u,
            1
        );
        $u = str_replace('use HasFactory;', 'use HasFactory;' . "\n" . '    use HasRoles;', $u);
        $u = str_replace("    protected \$table = 'users';", "    protected \$table = 'users';" . "\n\n" . "    protected \$guard_name = 'mes';", $u);
        file_put_contents($userPath, $u);
        echo "  ~ Models/User.php 已补 HasRoles + guard_name\n";
    }
}

echo "\n生成 {$written} 个模型。\n";
