<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Models\ShiftAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * 排班表（HR，M7 增补）。
 *
 * 对标 OrangeHRM Time → Work Shift / Odoo Planning：按周给员工排班，冲突按"人+日"唯一键避免。
 */
class RosterController extends Controller
{
    public function index(Request $request): View
    {
        $weekStart = $request->filled('week')
            ? Carbon::parse((string) $request->query('week'))->startOfWeek()
            : now()->startOfWeek();

        $dept = (string) $request->query('dept', '');

        $employees = Employee::query()
            ->with('department:id,name', 'position:id,name')
            ->where('status', 'active')
            ->when($dept !== '', fn ($q) => $q->where('department_id', (int) $dept))
            ->orderBy('employee_no')
            ->limit(30)
            ->get();

        $assignments = ShiftAssignment::query()
            ->with('shift:id,code,name,work_start,work_end')
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekStart->copy()->endOfWeek()->toDateString()])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->get();

        // [employee_id][Y-m-d] => assignment
        $grid = [];
        foreach ($assignments as $a) {
            $grid[$a->employee_id][$a->work_date->toDateString()] = $a;
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $weekStart->copy()->addDays($i);
        }

        return view('hr::attendance.roster', [
            'employees' => $employees,
            'shifts' => Shift::query()->where('status', 'active')->orderBy('code')->get(['id', 'code', 'name', 'work_start', 'work_end']),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'grid' => $grid,
            'days' => $days,
            'weekStart' => $weekStart,
            'dept' => $dept,
            'stat' => [
                'planned' => $assignments->count(),
                'people' => $assignments->pluck('employee_id')->unique()->count(),
                'leave' => $assignments->where('status', 'leave')->count(),
                'coverage' => $employees->count() > 0 ? round($assignments->count() / max(1, $employees->count() * 7) * 100, 1) : 0,
            ],
        ]);
    }

    /** 批量排班：一批人 × 一个日期区间 × 一个班次 */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:hr.employees,id'],
            'shift_id' => ['required', 'integer', 'exists:hr.shifts,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ], [], ['employee_ids' => '员工', 'shift_id' => '班次', 'from' => '开始日期', 'to' => '结束日期']);

        $from = Carbon::parse($data['from']);
        $to = Carbon::parse($data['to']);
        if ($from->diffInDays($to) > 31) {
            return back()->with('error', '一次最多排 31 天，跨度太大请分批。');
        }

        $count = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            foreach ($data['employee_ids'] as $employeeId) {
                ShiftAssignment::query()->updateOrCreate(
                    ['employee_id' => (int) $employeeId, 'work_date' => $d->toDateString()],
                    ['shift_id' => (int) $data['shift_id'], 'status' => 'planned']
                );
                $count++;
            }
        }

        return redirect()->route('hr.attendance.roster', ['week' => $from->startOfWeek()->toDateString()])
            ->with('status', "已排班 {$count} 人次。");
    }

    public function destroy(ShiftAssignment $assignment): RedirectResponse
    {
        $assignment->delete();

        return back()->with('status', '这条排班已删除。');
    }

    /** 标记实际出勤 / 请假 / 缺勤 */
    public function mark(Request $request, ShiftAssignment $assignment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:planned,actual,leave,absent'],
        ], [], ['status' => '状态']);

        $assignment->update(['status' => $data['status']]);

        return back()->with('status', '排班状态已更新。');
    }
}


