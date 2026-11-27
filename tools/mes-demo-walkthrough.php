<?php

/**
 * MES 九步演示主链路走查（M6-07）。
 *
 * 直接调服务层按规划书 §7 的九步跑一遍，每步断言关键数字，最后重跑种子复原演示数据。
 * 用法：
 *   php tools\mes-demo-walkthrough.php            # 走查后复原数据
 *   php tools\mes-demo-walkthrough.php --keep     # 保留现场，人工进页面看
 *
 * 这是一条可反复执行的验收脚本，不要把它当业务代码依赖。
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Mes\Models\Defect;
use App\Modules\Mes\Models\Inspection;
use App\Modules\Mes\Models\ProductionReport;
use App\Modules\Mes\Models\Shift;
use App\Modules\Mes\Models\User;
use App\Modules\Mes\Models\WorkOrder;
use App\Modules\Mes\Models\WorkOrderOperation;
use App\Modules\Mes\Services\ApsService;
use App\Modules\Mes\Services\InspectionService;
use App\Modules\Mes\Services\KanbanService;
use App\Modules\Mes\Services\ReportService;
use App\Modules\Mes\Services\WorkOrderService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$pass = 0;
$fail = 0;

/** 断言一步，失败不中断（走查要一次看完所有问题） */
function step(string $no, string $name, callable $fn): void
{
    global $pass, $fail;

    try {
        $detail = $fn();
        $pass++;
        echo "  [PASS] {$no} {$name} — {$detail}\n";
    } catch (Throwable $e) {
        $fail++;
        echo "  [FAIL] {$no} {$name} — {$e->getMessage()}\n";
    }
}

function ensure(bool $cond, string $msg): void
{
    if (! $cond) {
        throw new RuntimeException($msg);
    }
}

$kanban = app(KanbanService::class);
$orders = app(WorkOrderService::class);
$reportSvc = app(ReportService::class);
$inspSvc = app(InspectionService::class);
$aps = app(ApsService::class);

$admin = User::query()->where('email', 'admin@mes.demo')->first();
$qc = User::query()->where('email', 'qc@mes.demo')->first();
$lead = User::query()->where('email', 'lead@mes.demo')->first();
$shift = Shift::query()->where('is_active', true)->first();

echo "MES 九步演示走查（" . now()->format('Y-m-d H:i:s') . "）\n\n";

// 1 车间看板
step('1', '打开车间看板', function () use ($kanban) {
    $k = $kanban->snapshot();
    ensure($k['equip_total'] > 0, '看板没有设备数据');
    ensure($k['ok_today'] > 0, '看板今日产出为 0，演示会显得没生产');

    return "今日产出 {$k['ok_today']} 件 / 良率 {$k['yield_today']}% / 在制 {$k['wip_orders']} 张 / 设备 {$k['equip_running']}-{$k['equip_total']} / 异常 " . count($k['alerts']) . ' 条';
});

// 2 工单详情：工序时间轴 + 质检卡点
step('2', '点在制工单看工序进度', function () {
    $wo = WorkOrder::query()->where('status', 'in_progress')->orderBy('order_no')->first();
    ensure($wo !== null, '没有在制工单');

    $ops = $wo->operations()->get();
    ensure($ops->count() >= 4, "工单 {$wo->order_no} 工序数不足 4 道");

    $done = $ops->where('status', 'done')->count();
    $running = $ops->whereIn('status', ['in_progress', 'dispatched'])->count();

    return "{$wo->order_no}：{$ops->count()} 道工序，在产 {$running} 道 / 已完成 {$done} 道，完成数 {$wo->done_qty}/{$wo->plan_qty}";
});

// 3 下达 + 派工（用一张草稿单，客户能看到状态变化）
step('3', '主管下达并一键派工', function () use ($orders, $lead) {
    $draft = WorkOrder::query()->where('status', 'draft')->orderBy('order_no')->first();
    ensure($draft !== null, '没有草稿工单可演示下达');

    $r = $orders->release($draft);
    ensure($r['ok'], $r['message']);

    $total = $draft->operations()->count();
    ensure($total > 0, '下达后这张单没有任何工序');

    $n = $orders->dispatchAllPending($draft, $lead?->id, '一号车间 A 班');
    ensure($n > 0, '一键派工没有派出去任何工序');

    return "{$draft->order_no} 下达（新增工序 {$r['operations']} 道，合计 {$total} 道），派工 {$n} 道";
});

// 4 报工：进度条前进
step('4', '操作工报工', function () use ($reportSvc, $lead, $shift) {
    $op = WorkOrderOperation::query()
        ->where('status', 'in_progress')
        ->whereHas('workOrder', fn ($q) => $q->where('status', 'in_progress'))
        ->orderBy('done_qty')
        ->first();
    ensure($op !== null, '没有可报工的工序');

    $before = (float) $op->done_qty;
    $ok = min(20, max(1, (float) $op->plan_qty - $before));

    $reportSvc->create($op, [
        'ok_qty' => $ok,
        'defect_qty' => 1,
        'minutes' => 120,
        'shift_id' => $shift?->id,
        'remark' => '走查脚本报工',
    ], (int) ($lead?->id ?? 0));

    $op->refresh();
    ensure((float) $op->done_qty > $before, '报工后工序完成数没有前进');

    $wo = $op->workOrder()->first();
    ensure($wo !== null, '报工后找不到工单');

    return "工序 {$op->op_no} {$op->op_name}：{$before} → {$op->done_qty}，工单 {$wo->order_no} 累计 {$wo->done_qty}";
});

// 5 检验判定：超差 → 不合格 → 自动登记不良
step('5', '质检员判定超差项', function () use ($inspSvc, $qc) {
    $ins = Inspection::query()->whereNull('result')->orderBy('id')->first();
    ensure($ins !== null, '没有待判定检验单');

    $before = Defect::query()->count();
    $r = $inspSvc->submit($ins, [0 => '9999'], (int) ($qc?->id ?? 0));

    ensure($r['overall'] === 'fail', '超差项没有判成不合格');
    ensure(Defect::query()->count() === $before + 1, '不合格没有自动登记不良');
    ensure($r['defect_no'] !== null, '没有返回自动不良编号');

    return "{$ins->inspection_no} 判不合格（{$r['ng']} 项超差），自动登记不良 {$r['defect_no']}";
});

// 6 不良转返工
step('6', '不良转返工挂回原单', function () use ($orders) {
    $defect = Defect::query()->where('status', 'open')->orderByDesc('id')->first();
    ensure($defect !== null, '没有被自动登记的不良');

    $wo = $defect->workOrder()->first();
    ensure($wo !== null, '不良找不到来源工单');

    $rework = $orders->create([
        'product_material_id' => $wo->product_material_id,
        'plan_qty' => max(1, (float) $defect->qty),
        'remark' => "返工单：源自 {$wo->order_no} 的不良 {$defect->defect_code} × {$defect->qty}",
    ]);

    $defect->update(['disposal' => 'rework', 'status' => 'closed']);

    return "不良 #{$defect->id} 转返工 → 新单 {$rework->order_no}（{$rework->plan_qty} 件）";
});

// 7 回看板：数字变化
step('7', '回看板看变化', function () use ($kanban) {
    $k = $kanban->snapshot();
    ensure($k['reports_today'] > 0, '看板没有今日报工');
    ensure(Defect::query()->where('status', 'open')->count() >= 1 || $k['equip_running'] > 0, '看板异常区全是空的');

    return "今日产出 {$k['ok_today']} 件 / 良率 {$k['yield_today']}% / 报工 {$k['reports_today']} 笔 / 异常 " . count($k['alerts']) . ' 条';
});

// 8 排产甘特
step('8', '打开排产甘特 + 一键排产', function () use ($aps) {
    $r = $aps->rebuild(5);
    ensure($r['blocks'] > 0, '一键排产没有生成任何作业块');

    [$grid] = $aps->grid(5);
    ensure(count($grid) > 0, '甘特网格为空');

    return "生成 {$r['blocks']} 块，占用 {$r['minutes']} 分钟，窗口外 {$r['overflow_minutes']} 分钟";
});

// 9 三张报表
step('9', '打开三张报表', function () {
    $rows = DB::connection('mes')->table('production_reports')->count();
    ensure($rows > 0, '产量/良率报表没有报工数据');

    $defects = Defect::query()->count();
    ensure($defects > 0, '不良 Top5 没有数据');

    $std = DB::connection('mes')
        ->table('production_reports as r')
        ->join('work_order_operations as o', 'o.id', '=', 'r.operation_id')
        ->selectRaw('sum((r.ok_qty + r.defect_qty) * o.std_minutes) as s, sum(r.minutes) as a')
        ->first();
    ensure((float) $std->a > 0, '工时效率分母为 0');

    $eff = (float) $std->s > 0 ? round((float) $std->s / (float) $std->a * 100, 1) : 0;

    return "报工 {$rows} 笔 / 不良 {$defects} 条 / 工时效率 {$eff}%";
});

echo "\n结果：PASS {$pass} / FAIL {$fail}\n";

$keep = in_array('--keep', $argv, true);
if (! $keep) {
    echo "复原演示数据（重跑 DemoSeeder）…";
    Artisan::call('db:seed', [
        '--class' => 'App\Modules\Mes\Database\Seeders\DatabaseSeeder',
        '--database' => 'mes',
        '--force' => true,
    ]);
    echo " done\n";
} else {
    echo "已保留现场（--keep），可进页面看第 3~6 步产生的新单。\n";
}

exit($fail === 0 ? 0 : 1);
