<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 阶段占位页（S5 开发期间的中间态）。
 *
 * 作用：让导航里的每个入口都真实可达，客户点进去不会看到 404；
 * 页面上明确写出"本模块处于 M1/M2 阶段"，避免误以为系统坏了。
 * 随着各阶段完成，对应路由会逐步改指向真实控制器，本类随之缩小。
 */
class PlaceholderController extends Controller
{
    /** 路由名 → [页面标题, 计划阶段, 一句说明] */
    private const MAP = [
        'mes.kanban' => ['车间看板', 'M6', '工作中心 × 5 天排产甘特图、当班产出与异常墙。'],
        'mes.work-orders.index' => ['工单列表', 'M2', '工单号、成品、数量、状态、进度、计划日期，支持筛选与下达。'],
        'mes.work-orders.show' => ['工单详情', 'M2', '工序进度、报工记录、领料记录、检验记录四个 Tab。'],
        'mes.master.materials.index' => ['物料主数据', 'M1', 'MJ- 编码、规格、单位、分类、安全库存、现有库存。'],
        'mes.master.boms.index' => ['BOM', 'M1', '成品 → 子件清单，含用量、损耗率、行序。'],
        'mes.master.routings.index' => ['工艺路线', 'M1', '成品 → 工序序列（工序号 / 工作中心 / 标准工时 / 是否需检验）。'],
        'mes.master.work-centers.index' => ['工作中心', 'M1', '车间 / 产线、日产能（分钟）、状态。'],
        'mes.master.shifts.index' => ['班次', 'M1', '白班 / 夜班、起止时间、是否跨天。'],
        'mes.quality.inspections.index' => ['检验单', 'M4', 'IQC / IPQC / OQC 检验、判定、不良处置。'],
        'mes.equipment.index' => ['设备台账', 'M5', '设备状态、点检周期、报修记录、停机时长。'],
        'mes.import.index' => ['Excel 导入', 'M1', '物料 / BOM / 工单三套模板，上传→校验→预览→入库。'],
        'mes.system.users.index' => ['账号管理', 'M6', '班组账号、角色分配（班组长 / 质检 / 设备 / 计划）。'],
    ];

    public function show(Request $request): View
    {
        $name = (string) $request->route()?->getName();
        [$title, $phase, $desc] = self::MAP[$name] ?? ['该模块', '后续阶段', '本模块尚未开放。'];

        return view('mes::placeholder', [
            'title' => $title,
            'phase' => $phase,
            'desc' => $desc,
        ]);
    }
}
