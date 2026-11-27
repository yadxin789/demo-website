<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 阶段占位页（S6 开发期间的中间态）。
 *
 * 作用：让导航里的每个入口都真实可达，客户点进去不会看到 404；
 * 页面上明确写出"本模块处于 H1/H2 阶段"，避免误以为系统坏了。
 * 随着各阶段完成，对应路由会逐步改指向真实控制器，本类随之缩小。
 */
class PlaceholderController extends Controller
{
    /** 路由名 → [页面标题, 计划阶段, 一句说明] */
    private const MAP = [
        'hr.org.departments.index' => ['部门树', 'H1', '多级组织架构、部门负责人、编制数与在职数对比。'],
        'hr.org.positions.index' => ['岗位列表', 'H1', '岗位编码、职级、所属部门。'],
        'hr.org.headcounts.index' => ['编制管理', 'H1', '部门 × 岗位的编制与缺口，缺口红字提醒。'],
        'hr.employees.index' => ['员工列表', 'H2', '工号 HR2026xxx、姓名、部门、岗位、在职状态。'],
        'hr.employees.contracts' => ['合同管理', 'H2', '合同编号、类型、起止日期，30/60/90 天到期提醒。'],
        'hr.employees.movements' => ['异动记录', 'H2', '入职 / 转正 / 调岗 / 晋升 / 离职流水。'],
        'hr.attendance.shifts' => ['班次管理', 'H3', '标准班 / 早班 / 夜班，打卡时间窗与迟到容忍。'],
        'hr.attendance.records' => ['打卡记录', 'H3', '按日 / 人 / 部门筛选，迟到早退标记。'],
        'hr.attendance.exceptions' => ['考勤异常', 'H3', '缺卡 / 迟到 / 早退 / 旷工，逐条处理。'],
        'hr.attendance.overtimes' => ['加班登记', 'H3', '加班申请与审批，工作日 / 周末 / 节假日。'],
        'hr.leaves.index' => ['请假列表', 'H3', '年假 / 病假 / 事假 / 调休，待审 / 已批 / 驳回。'],
        'hr.leaves.balances' => ['假期余额', 'H3', '每人年假、调休的总额 / 已用 / 剩余。'],
        'hr.payroll.structures' => ['薪资结构', 'H4', '岗位薪资构成：基本 / 岗位 / 绩效 / 补贴。'],
        'hr.payroll.runs.index' => ['工资批次', 'H4', '按月生成的一批工资单，草稿 → 确认 → 已发。'],
        'hr.payroll.my' => ['我的工资条', 'H4', '员工自助查看本月工资条（只能看自己）。'],
        'hr.recruit.requisitions' => ['招聘需求', 'H5', '需求编号、岗位、人数、紧急程度、进度。'],
        'hr.recruit.candidates' => ['候选人', 'H5', '简历要点、来源、阶段（筛选 / 面试 / offer / 入职）。'],
        'hr.recruit.interviews' => ['面试记录', 'H5', '面试官、轮次、评分、结论、反馈。'],
        'hr.performance.cycles' => ['绩效周期', 'H5', '季度 / 年度周期、参与人、状态。'],
        'hr.performance.reviews' => ['绩效评分', 'H5', '指标 + 权重 + 自评 / 上级评，加权算总分。'],
        'hr.performance.results' => ['绩效结果', 'H5', '分布、排名、与调薪挂钩。'],
        'hr.reports.headcount' => ['人数结构报表', 'H6', '部门 / 职级 / 司龄分布。'],
        'hr.reports.attendance' => ['考勤汇总报表', 'H6', '部门 × 月份出勤率、迟到率、加班时长。'],
        'hr.reports.payroll' => ['人力成本报表', 'H6', '部门 × 月份人力成本柱状图。'],
        'hr.import.index' => ['Excel 导入', 'H4', '员工花名册、考勤打卡两套模板；工资单、考勤月报导出。'],
        'hr.system.users.index' => ['账号管理', 'H6', 'HR 账号、角色分配、状态。'],
        'hr.system.roles.index' => ['角色权限', 'H6', '五角色 × 权限矩阵。'],
        'hr.system.notifications' => ['消息通知', 'H6', '审批与到期提醒留档（演示版不发实时推送）。'],
    ];

    public function show(Request $request): View
    {
        $name = (string) $request->route()?->getName();
        [$title, $phase, $desc] = self::MAP[$name] ?? ['该模块', '后续阶段', '本模块尚未开放。'];

        return view('hr::placeholder', [
            'title' => $title,
            'phase' => $phase,
            'desc' => $desc,
        ]);
    }
}
