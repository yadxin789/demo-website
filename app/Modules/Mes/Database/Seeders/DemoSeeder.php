<?php

declare(strict_types=1);

namespace App\Modules\Mes\Database\Seeders;

use App\Modules\Mes\Models\ApsSchedule;
use App\Modules\Mes\Models\Defect;
use App\Modules\Mes\Models\Equipment;
use App\Modules\Mes\Models\EquipmentCheck;
use App\Modules\Mes\Models\EquipmentRepair;
use App\Modules\Mes\Models\Inspection;
use App\Modules\Mes\Models\InspectionResult;
use App\Modules\Mes\Models\InspectionTemplate;
use App\Modules\Mes\Models\Material;
use App\Modules\Mes\Models\MaterialIssue;
use App\Modules\Mes\Models\MaterialIssueLine;
use App\Modules\Mes\Models\ProductionReport;
use App\Modules\Mes\Models\Routing;
use App\Modules\Mes\Models\Shift;
use App\Modules\Mes\Models\User;
use App\Modules\Mes\Models\WorkCenter;
use App\Modules\Mes\Models\WorkOrder;
use App\Modules\Mes\Models\WorkOrderOperation;
use Illuminate\Database\Seeder;

/**
 * MES 演示业务数据（M2-10 / M3 / M4 / M5）。
 *
 * 20 张工单覆盖四种状态，并刻意做出三张"有故事"的单：
 *   WO-2026-0007 卡在质检（工序 done 但检验未判定）
 *   WO-2026-0011 缺料停线（领料行 pending 多于已发）
 *   WO-2026-0016 良率偏低（不良数偏高）
 */
class DemoSeeder extends Seeder
{
    /** 工单状态分布 */
    private const STATUS_MIX = [
        'draft' => 3,
        'released' => 5,
        'in_progress' => 8,
        'completed' => 4,
    ];

    public function run(): void
    {
        $this->resetBusinessData();

        $shiftA = Shift::query()->where('code', 'SH-A')->first();
        $admin = User::query()->where('email', 'admin@mes.demo')->first();
        $lead = User::query()->where('email', 'lead@mes.demo')->first() ?: $admin;
        $qc = User::query()->where('email', 'qc@mes.demo')->first() ?: $admin;

        $templates = $this->seedTemplates();
        $this->seedEquipments();

        $seq = 0;
        foreach (self::STATUS_MIX as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $seq++;
                $this->makeWorkOrder($seq, $status, $shiftA, $admin, $lead, $qc, $templates);
            }
        }

        $this->seedAps();
    }

    /**
     * 清空全部演示业务数据（严格按外键依赖倒序）。
     *
     * 工序被 inspections / defects / aps_schedules / production_reports 从
     * operation_id 引用且没有级联，顺序错了就会撞 FK，所以统一在这里处理，
     * 让 DemoSeeder 可重复执行。
     */
    private function resetBusinessData(): void
    {
        $db = \DB::connection('mes');

        foreach ([
            'inspection_results',
            'defects',
            'inspections',
            'production_reports',
            'material_issue_lines',
            'material_issues',
            'aps_schedules',
            'equipment_checks',
            'equipment_repairs',
            'work_order_operations',
            'work_orders',
        ] as $table) {
            $db->table($table)->delete();
        }
    }

    /** @return array<string, InspectionTemplate> */
    private function seedTemplates(): array
    {
        $defs = [
            'IQC-ELEC' => ['来料电性能检验', 'IQC', 0, [
                ['name' => '阻值', 'std' => '10K±5%', 'min' => '9.5', 'max' => '10.5', 'unit' => 'KΩ', 'expect' => '10.0'],
                ['name' => '绝缘电阻', 'std' => '>100MΩ', 'min' => '100', 'unit' => 'MΩ', 'expect' => '500'],
            ]],
            'IPQC-WELD' => ['焊接质量检验', 'IPQC', 30, [
                ['name' => '焊点光泽', 'std' => '饱满光亮', 'expect' => '合格'],
                ['name' => '虚焊数', 'std' => '0', 'unit' => '处', 'expect' => '0'],
            ]],
            'OQC-FUNC' => ['成品功能检验', 'OQC', 50, [
                ['name' => '通电测试', 'std' => '正常启动', 'expect' => '正常'],
                ['name' => '通讯测试', 'std' => 'Modbus 应答', 'expect' => '应答正常'],
                ['name' => '外观', 'std' => '无划伤', 'expect' => '合格'],
            ]],
        ];

        $map = [];
        foreach ($defs as $code => [$name, $type, $opNo, $items]) {
            /** @var InspectionTemplate $t */
            $t = InspectionTemplate::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'op_no' => $opNo ?: null,
                    'type' => $type,
                    'items' => array_map(fn ($it) => [
                        'name' => $it['name'],
                        'std' => $it['std'],
                        'min' => $it['min'] ?? '',
                        'max' => $it['max'] ?? '',
                        'unit' => $it['unit'] ?? '',
                        'expect' => $it['expect'] ?? '',
                    ], $items),
                    'is_active' => true,
                ]
            );
            $map[$code] = $t;
        }

        return $map;
    }

    private function seedEquipments(): void
    {
        $centers = WorkCenter::query()->get()->keyBy('code');
        $admin = User::query()->where('email', 'admin@mes.demo')->first();
        $eqUser = User::query()->where('email', 'eq@mes.demo')->first() ?: $admin;

        $defs = [
            ['EQ-SMT-01', 'SMT 贴片机 YAMAHA', 'WC-SMT', 'running'],
            ['EQ-SMT-02', '回流焊炉 8 温区', 'WC-SMT', 'running'],
            ['EQ-THT-01', '波峰焊机', 'WC-THT', 'idle'],
            ['EQ-ASM-01', '自动锁螺丝机', 'WC-ASM', 'running'],
            ['EQ-ASM-02', '气动压装机', 'WC-ASM', 'repairing'],
            ['EQ-TST-01', '在线功能测试台 FCT-1', 'WC-TST', 'running'],
            ['EQ-TST-02', '老化测试柜', 'WC-TST', 'idle'],
            ['EQ-TST-03', '自动包装线', 'WC-TST', 'running'],
            ['EQ-SMT-03', '全自动锡膏印刷机', 'WC-SMT', 'repairing'],
            ['EQ-THT-02', '选择性波峰焊', 'WC-THT', 'running'],
            ['EQ-ASM-03', '精密点胶机', 'WC-ASM', 'running'],
            ['EQ-TST-04', 'X-Ray 检测机', 'WC-TST', 'running'],
        ];

        foreach ($defs as [$code, $name, $wcCode, $status]) {
            $eq = Equipment::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'work_center_id' => $centers[$wcCode]->id ?? null,
                    'status' => $status,
                    'check_cycle_days' => 30,
                    'last_check_at' => now()->subDays(random_int(1, 25)),
                    'purchased_on' => now()->subYears(3)->toDateString(),
                ]
            );

            // 每台设备近 5 天各一条点检（共 60 条），异常记录留给有故障的机台
            for ($d = 4; $d >= 0; $d--) {
                EquipmentCheck::query()->create([
                    'equipment_id' => $eq->id,
                    'checked_at' => now()->subDays($d)->setTime(8, 30),
                    'checker_id' => $eqUser?->id,
                    'result' => ($code === 'EQ-ASM-02' && $d === 0) ? 'ng' : 'ok',
                    'remark' => ($code === 'EQ-ASM-02' && $d === 0)
                        ? '气压偏低，压装不到位，已报修'
                        : '气压 / 油位 / 温度 / 异响 四项正常',
                ]);
            }

            $eq->update(['last_check_at' => now()->setTime(8, 30)]);
        }

        // 5 张报修：2 张未闭环（对应两台维修中设备），其余已修复或关单
        $repairs = [
            ['EQ-SMT-01', '吸嘴真空不足，抛料率偏高', 'fixed', 45],
            ['EQ-ASM-02', '气压偏低，压装不到位', 'handling', 120],
            ['EQ-SMT-03', '刮刀磨损，印刷厚度不均', 'handling', 0],
            ['EQ-THT-01', '预热区加热管损坏，整机停机', 'closed', 320],
            ['EQ-TST-02', '测试治具接触不良，误判率高', 'fixed', 60],
        ];

        foreach ($repairs as [$code, $desc, $repairStatus, $down]) {
            $eq = Equipment::query()->where('code', $code)->first();
            if (! $eq) {
                continue;
            }

            $closed = in_array($repairStatus, ['fixed', 'closed'], true);

            EquipmentRepair::query()->create([
                'equipment_id' => $eq->id,
                'fault_desc' => $desc,
                'reported_by' => $admin?->id,
                'handler_id' => $eqUser?->id,
                'down_minutes' => $down,
                'status' => $repairStatus,
                'fixed_at' => $closed ? now()->subDays(1) : null,
            ]);
        }
    }

    private function makeWorkOrder(
        int $seq,
        string $status,
        ?Shift $shift,
        ?User $admin,
        ?User $lead,
        ?User $qc,
        array $templates
    ): void {
        $fgIndex = (($seq - 1) % 8) + 1;
        $fgCode = sprintf('MJ-FG-%03d', $fgIndex);

        $product = Material::query()->where('code', $fgCode)->first();
        $routing = Routing::query()->where('code', 'RT-'.substr($fgCode, 3))->first();
        $bom = \App\Modules\Mes\Models\Bom::query()->where('code', 'BOM-'.substr($fgCode, 3))->first();

        if (! $product) {
            return;
        }

        $planQty = 200 + (($seq % 5) * 150);          // 200 ~ 800
        $doneRatio = match ($status) {
            'draft', 'released' => 0,
            'in_progress' => 0.35 + (($seq % 4) / 10),
            'completed' => 1,
            default => 0,
        };
        $doneQty = (int) floor($planQty * $doneRatio);
        $defectQty = $seq === 16 ? (int) floor($doneQty * 0.06) : (int) floor($doneQty * 0.01);

        $orderNo = sprintf('WO-2026-%04d', $seq);

        /** @var WorkOrder $wo */
        $wo = WorkOrder::query()->updateOrCreate(
            ['order_no' => $orderNo],
            [
                'product_material_id' => $product->id,
                'bom_id' => $bom?->id,
                'routing_id' => $routing?->id,
                'plan_qty' => $planQty,
                'done_qty' => $doneQty,
                'defect_qty' => $defectQty,
                'status' => $status,
                'plan_start' => now()->subDays(6 - ($seq % 5))->toDateString(),
                'plan_end' => now()->addDays(2 + ($seq % 6))->toDateString(),
                'priority' => 1 + ($seq % 5),
                'customer' => ['苏州华勤', '昆山立讯', '无锡先导', '宁波均胜'][$seq % 4],
                'remark' => null,
            ]
        );

        // ---- 工序 ----
        // 先删报工：production_reports.operation_id 外键指向工序，顺序反了会撞外键
        $wo->reports()->delete();
        $wo->operations()->delete();
        $ops = $routing?->operations ?? collect();
        $opCount = max(1, $ops->count());

        foreach ($ops as $idx => $routeOp) {
            // 已完工：每道工序都做满计划量；
            // 在制：末道 = 工单完成数（瓶颈），前道有在制品堆积（1.35 倍但不超过计划量）。
            if ($status === 'completed') {
                $opDone = $planQty;
            } elseif ($idx + 1 === $opCount) {
                $opDone = $doneQty;
            } else {
                $opDone = min($planQty, (int) ceil($doneQty * 1.35));
            }

            $opStatus = match (true) {
                $status === 'draft' => 'pending',
                $status === 'released' => 'dispatched',
                $status === 'completed' => 'done',
                $opDone >= $planQty => 'done',
                $opDone > 0 => 'in_progress',
                default => 'dispatched',
            };

            WorkOrderOperation::query()->create([
                'work_order_id' => $wo->id,
                'op_no' => $routeOp->op_no,
                'op_name' => $routeOp->op_name,
                'work_center_id' => $routeOp->work_center_id,
                'plan_qty' => $planQty,
                'done_qty' => $opDone,
                'defect_qty' => (int) floor($defectQty / $opCount),
                'status' => $opStatus,
                'assignee_id' => $lead?->id,
                'team' => '一号车间 A 班',
                'std_minutes' => $routeOp->std_minutes,
                'started_at' => $opDone > 0 ? now()->subDays(1) : null,
                'finished_at' => $opStatus === 'done' ? now()->subHours(2) : null,
            ]);
        }

        // ---- 报工 ----
        $wo->reports()->delete();
        if ($doneQty > 0) {
            foreach (range(1, min(3, max(1, (int) floor($doneQty / 100)))) as $r) {
                ProductionReport::query()->create([
                    'work_order_id' => $wo->id,
                    'operation_id' => $wo->operations()->where('status', 'done')->value('id'),
                    'user_id' => $lead?->id,
                    'shift_id' => $shift?->id,
                    'ok_qty' => max(1, (int) floor($doneQty / 3)),
                    'defect_qty' => max(0, (int) floor($defectQty / 3)),
                    'minutes' => 90,
                    'reported_at' => now()->subHours(24 - $r * 4),
                ]);
            }
        }

        // ---- 领料（除草稿外都要领料；0011 故意只发一半，制造缺料停线）----
        // 0004/0005 两张已下达的单故意不建领料单，留给演示"工单详情一键生成领料单"
        $wo->issues()->delete();
        if ($status !== 'draft' && $bom && ! in_array($seq, [4, 5], true)) {
            /** @var MaterialIssue $issue */
            $issue = MaterialIssue::query()->create([
                'issue_no' => 'MI-'.substr($orderNo, 3),
                'work_order_id' => $wo->id,
                'status' => $seq === 11 ? 'partial' : 'issued',
                'issued_at' => now()->subDays(2),
                'issued_by' => $admin?->id,
            ]);

            foreach ($bom->lines as $line) {
                $required = (float) $line->qty_per * $planQty * (1 + (float) $line->loss_rate);
                $issued = $seq === 11 ? round($required * 0.5, 2) : $required;

                MaterialIssueLine::query()->create([
                    'material_issue_id' => $issue->id,
                    'material_id' => $line->component_material_id,
                    'required_qty' => $required,
                    'issued_qty' => $issued,
                    'status' => $seq === 11 ? 'partial' : 'issued',
                ]);
            }
        }

        // ---- 检验 ----
        $wo->inspections()->delete();
        if (in_array($status, ['in_progress', 'completed'], true) || $seq === 7) {
            $type = $status === 'completed' ? 'OQC-FUNC' : 'IPQC-WELD';
            $tpl = $templates[$type] ?? null;
            $seq === 7 ? $this->pendingInspection($wo, $templates['IPQC-WELD'] ?? null, $lead) : null;
            // 再留两张待判定，让工作台的"质检待办"有内容
            in_array($seq, [10, 14], true)
                ? $this->pendingInspection($wo, $templates['OQC-FUNC'] ?? null, $lead, '-P2')
                : null;

            if ($seq !== 7) {
                /** @var Inspection $insp */
                $insp = Inspection::query()->create([
                    'inspection_no' => 'QC-'.substr($orderNo, 3),
                    'work_order_id' => $wo->id,
                    'operation_id' => $wo->operations()->where('status', 'done')->value('id'),
                    'template_id' => $tpl?->id,
                    'type' => $tpl?->type ?? 'IPQC',
                    'result' => $status === 'completed' ? 'pass' : ($seq === 16 ? 'fail' : 'pass'),
                    'inspector_id' => $qc?->id,
                    'inspected_at' => now()->subHours(6),
                ]);

                InspectionResult::query()->create([
                    'inspection_id' => $insp->id,
                    'item_name' => '焊点光泽',
                    'std_value' => '饱满光亮',
                    'actual_value' => $seq === 16 ? '偏暗' : '合格',
                    'verdict' => $seq === 16 ? 'ng' : 'ok',
                ]);
            }
        }

        // ---- 补充检验：IQC / IPQC 各一张，让检验页覆盖三种类型（共 25 张）----
        if ($status === 'in_progress') {
            $this->extraInspection($wo, $templates['IQC-ELEC'] ?? null, $qc, '-IQC', 'IQC');
        }
        if ($status === 'completed') {
            $this->extraInspection($wo, $templates['IPQC-WELD'] ?? null, $qc, '-IPQC', 'IPQC');
        }

        // ---- 不良记录（16 号单良率偏低）----
        $wo->defects()->delete();
        if ($seq === 16) {
            Defect::query()->create([
                'work_order_id' => $wo->id,
                'operation_id' => $wo->operations()->where('status', 'done')->value('id'),
                'defect_code' => 'DF-SOLDER',
                'qty' => $defectQty,
                'disposal' => 'rework',
                'cause' => '回流焊温区 5 偏低，焊点偏暗',
                'owner_id' => $qc?->id,
                'status' => 'open',
            ]);
        }

        // ---- 其他不良：覆盖返工/报废、未处置/处置中/已结案三种状态 ----
        $extraDefects = [
            9 => [
                ['WELD-COLD', 3, 'rework', 'handling', '波峰焊预热不足'],
                ['SOLDER-BRIDGE', 4, 'rework', 'open', '钢网开口偏大导致连锡'],
            ],
            10 => [['COMP-SHIFT', 2, 'scrap', 'closed', '贴片机吸嘴偏移']],
            11 => [
                ['WELD-COLD', 5, 'rework', 'handling', '波峰焊预热不足'],
                ['SCRATCH', 3, 'scrap', 'closed', '周转箱划伤'],
            ],
            12 => [
                ['FUNC-FAIL', 2, 'rework', 'open', '测试治具接触不良'],
                ['COMP-SHIFT', 1, 'scrap', 'closed', '手贴元件偏移'],
            ],
            13 => [['COMP-SHIFT', 6, 'rework', 'closed', '换料时元件偏移']],
            14 => [
                ['SOLDER-BRIDGE', 8, 'rework', 'open', '锡膏印刷偏厚'],
                ['WELD-COLD', 2, 'scrap', 'closed', '手焊温度不足'],
                ['FUNC-FAIL', 1, 'rework', 'handling', '功能测试偶发不通'],
            ],
            15 => [['SCRATCH', 4, 'scrap', 'open', '包装前外观划伤']],
            16 => [
                ['WELD-COLD', 6, 'rework', 'open', '回流焊温区 5 偏低'],
                ['FUNC-FAIL', 3, 'rework', 'handling', '老化测试超时'],
                ['SCRATCH', 2, 'scrap', 'closed', '返修后外观不良'],
            ],
        ];

        foreach ($extraDefects[$seq] ?? [] as [$code, $qty, $disposal, $defectStatus, $cause]) {
            Defect::query()->create([
                'work_order_id' => $wo->id,
                'operation_id' => $wo->operations()->where('status', 'done')->value('id'),
                'defect_code' => $code,
                'qty' => $qty,
                'disposal' => $disposal,
                'cause' => $cause,
                'owner_id' => $qc?->id,
                'status' => $defectStatus,
            ]);
        }
    }

    /** 补一张已判合格的检验单（带一条实测明细），用于覆盖 IQC / IPQC 类型 */
    private function extraInspection(WorkOrder $wo, ?InspectionTemplate $tpl, ?User $qc, string $suffix, string $type): void
    {
        /** @var Inspection $insp */
        $insp = Inspection::query()->create([
            'inspection_no' => 'QC-' . substr($wo->order_no, 3) . $suffix,
            'work_order_id' => $wo->id,
            'operation_id' => $wo->operations()->where('status', 'done')->value('id'),
            'template_id' => $tpl?->id,
            'type' => $type,
            'result' => 'pass',
            'inspector_id' => $qc?->id,
            'inspected_at' => now()->subHours(8),
        ]);

        $first = (array) ($tpl?->items ?? [])[0] ?? ['name' => '外观', 'std' => '合格', 'expect' => '合格'];

        InspectionResult::query()->create([
            'inspection_id' => $insp->id,
            'item_name' => (string) ($first['name'] ?? '外观'),
            'std_value' => (string) ($first['std'] ?? ''),
            'actual_value' => (string) ($first['expect'] ?? '合格'),
            'verdict' => 'ok',
        ]);
    }

    /** 0011 号单之外的"卡质检"场景：工序已完工，检验仍 pending */
    private function pendingInspection(WorkOrder $wo, ?InspectionTemplate $tpl, ?User $lead, string $suffix = '-P'): void
    {
        /** @var Inspection $insp */
        $insp = Inspection::query()->create([
            'inspection_no' => 'QC-' . substr($wo->order_no, 3) . $suffix,
            'work_order_id' => $wo->id,
            'operation_id' => $wo->operations()->where('status', 'done')->value('id'),
            'template_id' => $tpl?->id,
            'type' => $tpl?->type ?? 'IPQC',
            'result' => null,
            'inspector_id' => null,
            'inspected_at' => null,
        ]);

        InspectionResult::query()->create([
            'inspection_id' => $insp->id,
            'item_name' => '虚焊数',
            'std_value' => '0',
            'actual_value' => null,
            'verdict' => 'ok',
        ]);
    }

    private function seedAps(): void
    {
        ApsSchedule::query()->delete();

        $centers = WorkCenter::query()->get();
        $orders = WorkOrder::query()
            ->whereIn('status', ['draft', 'released', 'in_progress'])
            ->orderBy('priority')
            ->limit(12)
            ->get();

        $cursor = [];
        foreach ($centers as $c) {
            $cursor[$c->id] = now()->startOfDay()->setTime(8, 0);
        }

        $seq = 0;
        foreach ($orders as $wo) {
            foreach ($wo->operations as $op) {
                $centerId = $op->work_center_id ?: $centers->first()->id;
                $duration = max(30, (int) ($op->std_minutes * ($wo->plan_qty / 60)));

                $start = $cursor[$centerId] ?? now()->startOfDay()->setTime(8, 0);
                $end = (clone $start)->addMinutes($duration);

                ApsSchedule::query()->create([
                    'work_order_id' => $wo->id,
                    'operation_id' => $op->id,
                    'work_center_id' => $centerId,
                    'start_at' => $start,
                    'end_at' => $end,
                    'seq' => ++$seq,
                ]);

                // 下一道工序紧接着排，超过 12 小时就顺延到次日 08:00
                $cursor[$centerId] = $end->minute >= 0 ? (clone $end) : (clone $end);
                if ($cursor[$centerId]->hour >= 20) {
                    $cursor[$centerId] = $cursor[$centerId]->addDay()->setTime(8, 0);
                }
            }
        }
    }
}
