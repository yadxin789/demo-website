<?php

declare(strict_types=1);

namespace App\Modules\Mes\Database\Seeders;

use App\Modules\Mes\Models\Bom;
use App\Modules\Mes\Models\BomLine;
use App\Modules\Mes\Models\Material;
use App\Modules\Mes\Models\Routing;
use App\Modules\Mes\Models\RoutingOperation;
use App\Modules\Mes\Models\Shift;
use App\Modules\Mes\Models\WorkCenter;
use Illuminate\Database\Seeder;

/**
 * MES 主数据（M1-09 / M1-10）。
 *
 * 规模：物料 40（成品 15 / 半成品 10 / 原料 15）、工作中心 4、班次 2、
 *       BOM 8（每个 3–6 行）、工艺 8（每个 3–5 道工序）。
 * 编码：MC-02 —— 物料 MJ- 前缀，与 ERP 的 MAT- 明确区分。
 */
class MasterSeeder extends Seeder
{
    /** 成品 15 个 */
    private const FINISHED = [
        '智能温控器 TC-200', 'PLC 控制模块 CM-8', '工业网关 GW-4G', '伺服驱动器 SD-750',
        '多路传感器模块 SM-16', '变频器 VFD-11K', '工业触摸屏 HMI-10', '远程 IO 模块 RIO-32',
        '电机驱动板 MD-500', '电源管理模块 PMU-24', '数据采集卡 DAQ-8', '无线通讯模块 WM-LoRa',
        '精密减速机 RG-60', '激光测距模块 LD-50', '工控机箱 IPC-4U',
    ];

    /** 半成品 10 个 */
    private const SEMI = [
        '主控板 PCBA-A', '电源板 PCBA-B', '驱动板 PCBA-C', '显示板 PCBA-D', '通讯板 PCBA-E',
        '机壳组件 HS-01', '按键面板 KP-02', '线束组件 WH-03', '散热组件 CL-04', '包装组件 PK-05',
    ];

    /** 原料 15 个 */
    private const RAW = [
        '贴片电阻 0805 10K', '贴片电容 0603 100nF', '主控芯片 MCU-STM32', '电源芯片 LDO-3V3',
        'MOS 管 N-60V', '晶振 16MHz', '连接器 20P', 'PCB 板 4 层', '焊锡丝 0.8mm',
        '铝型材 6063', 'ABS 塑料粒子', '不锈钢螺丝 M3', '硅胶密封圈', '屏蔽罩 20x20', '包材纸箱',
    ];

    private const WORK_CENTERS = [
        ['WC-SMT', 'SMT 贴片线', '一号车间', 480],
        ['WC-THT', '插件焊接线', '一号车间', 450],
        ['WC-ASM', '整机装配线', '二号车间', 480],
        ['WC-TST', '测试与包装线', '二号车间', 420],
    ];

    private const SHIFTS = [
        ['SH-A', '白班', '08:00', '20:00', false],
        ['SH-B', '夜班', '20:00', '08:00', true],
    ];

    public function run(): void
    {
        $materials = $this->seedMaterials();
        $centers = $this->seedWorkCenters();
        $this->seedShifts();
        $this->seedBoms($materials);
        $this->seedRoutings($materials, $centers);
    }

    /** @return array<string, Material> 编码 => 模型 */
    private function seedMaterials(): array
    {
        $map = [];

        $groups = [
            ['FG', 'finished', self::FINISHED, 'PCS', 120],
            ['SM', 'semi', self::SEMI, 'PCS', 200],
            ['RM', 'raw', self::RAW, 'PCS', 3000],
        ];

        foreach ($groups as [$prefix, $category, $names, $unit, $safety]) {
            foreach ($names as $i => $name) {
                $code = sprintf('MJ-%s-%03d', $prefix, $i + 1);
                /** @var Material $m */
                $m = Material::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'name' => $name,
                        'spec' => $category === 'raw' ? '标准规格' : '工业级',
                        'unit' => $unit,
                        'category' => $category,
                        'safety_stock' => $safety,
                        'stock_qty' => $safety * 1.5,
                        'std_cost' => $category === 'finished' ? 680 : ($category === 'semi' ? 210 : 8),
                        'is_active' => true,
                    ]
                );
                $map[$code] = $m;
            }
        }

        // 成品编码 → 名称，供 BOM / 工艺引用
        return $map;
    }

    /** @return array<string, WorkCenter> */
    private function seedWorkCenters(): array
    {
        $map = [];
        foreach (self::WORK_CENTERS as [$code, $name, $workshop, $capacity]) {
            /** @var WorkCenter $wc */
            $wc = WorkCenter::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'workshop' => $workshop, 'daily_capacity' => $capacity, 'status' => 'active']
            );
            $map[$code] = $wc;
        }

        return $map;
    }

    private function seedShifts(): void
    {
        foreach (self::SHIFTS as [$code, $name, $start, $end, $cross]) {
            Shift::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'start_time' => $start, 'end_time' => $end, 'cross_day' => $cross, 'is_active' => true]
            );
        }
    }

    /** @param array<string, Material> $materials */
    private function seedBoms(array $materials): void
    {
        // 8 个成品 BOM：成品编码 => [[子件编码, 用量, 损耗率], ...]
        $specs = [
            'MJ-FG-001' => [['MJ-SM-001', 1, 0.01], ['MJ-SM-006', 1, 0.01], ['MJ-RM-008', 1, 0.02], ['MJ-RM-015', 1, 0]],
            'MJ-FG-002' => [['MJ-SM-001', 1, 0.01], ['MJ-RM-003', 1, 0.03], ['MJ-RM-007', 1, 0.02], ['MJ-RM-009', 0.05, 0.05]],
            'MJ-FG-003' => [['MJ-SM-005', 1, 0.01], ['MJ-RM-012', 4, 0.02], ['MJ-RM-014', 2, 0.02], ['MJ-RM-015', 1, 0]],
            'MJ-FG-004' => [['MJ-SM-002', 1, 0.01], ['MJ-SM-003', 1, 0.01], ['MJ-RM-005', 2, 0.03], ['MJ-RM-010', 0.3, 0.05], ['MJ-RM-011', 0.2, 0.05]],
            'MJ-FG-005' => [['MJ-SM-001', 1, 0.01], ['MJ-RM-001', 8, 0.02], ['MJ-RM-002', 6, 0.02], ['MJ-RM-004', 2, 0.02]],
            'MJ-FG-006' => [['MJ-SM-002', 1, 0.01], ['MJ-SM-003', 1, 0.01], ['MJ-RM-010', 0.5, 0.05], ['MJ-RM-012', 6, 0.02]],
            'MJ-FG-007' => [['MJ-SM-004', 1, 0.01], ['MJ-SM-007', 1, 0.01], ['MJ-RM-008', 1, 0.02], ['MJ-RM-013', 2, 0.02]],
            'MJ-FG-008' => [['MJ-SM-005', 1, 0.01], ['MJ-RM-001', 10, 0.02], ['MJ-RM-006', 2, 0.02], ['MJ-RM-014', 2, 0.02]],
        ];

        foreach ($specs as $fgCode => $lines) {
            if (! isset($materials[$fgCode])) {
                continue;
            }

            /** @var Bom $bom */
            $bom = Bom::query()->updateOrCreate(
                ['code' => 'BOM-'.substr($fgCode, 3)],
                [
                    'product_material_id' => $materials[$fgCode]->id,
                    'version' => 'V1',
                    'status' => 'active',
                    'remark' => '演示 BOM',
                ]
            );

            $bom->lines()->delete();

            foreach ($lines as $idx => [$compCode, $qty, $loss]) {
                if (! isset($materials[$compCode])) {
                    continue;
                }

                BomLine::query()->create([
                    'bom_id' => $bom->id,
                    'component_material_id' => $materials[$compCode]->id,
                    'qty_per' => $qty,
                    'loss_rate' => $loss,
                    'seq' => ($idx + 1) * 10,
                ]);
            }
        }
    }

    /**
     * @param  array<string, Material>  $materials
     * @param  array<string, WorkCenter>  $centers
     */
    private function seedRoutings(array $materials, array $centers): void
    {
        // 8 个成品工艺：标准电子制造流程
        $common = [
            ['10', 'SMT 贴片', 'WC-SMT', 45, false],
            ['20', '回流焊接', 'WC-SMT', 30, true],
            ['30', '插件焊接', 'WC-THT', 40, true],
            ['40', '整机装配', 'WC-ASM', 60, false],
            ['50', '功能测试', 'WC-TST', 35, true],
            ['60', '包装入库', 'WC-TST', 20, false],
        ];

        for ($i = 1; $i <= 8; $i++) {
            $fgCode = sprintf('MJ-FG-%03d', $i);
            if (! isset($materials[$fgCode])) {
                continue;
            }

            // 前 3 个成品走完整 6 道工序，其余按 4–5 道，制造"不同复杂度"的真实感
            $ops = $i <= 3 ? $common : array_slice($common, 0, 4 + ($i % 2));

            /** @var Routing $routing */
            $routing = Routing::query()->updateOrCreate(
                ['code' => 'RT-'.substr($fgCode, 3)],
                [
                    'product_material_id' => $materials[$fgCode]->id,
                    'version' => 'V1',
                    'status' => 'active',
                ]
            );

            $routing->operations()->delete();

            foreach ($ops as [$opNo, $opName, $wcCode, $minutes, $inspect]) {
                if (! isset($centers[$wcCode])) {
                    continue;
                }

                RoutingOperation::query()->create([
                    'routing_id' => $routing->id,
                    'op_no' => (int) $opNo,
                    'op_name' => $opName,
                    'work_center_id' => $centers[$wcCode]->id,
                    'std_minutes' => $minutes,
                    'inspection_required' => $inspect,
                ]);
            }
        }
    }
}
