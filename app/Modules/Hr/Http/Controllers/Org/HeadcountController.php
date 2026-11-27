<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers\Org;

use App\Modules\Hr\Http\Controllers\Controller;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Headcount;
use App\Modules\Hr\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 编制缺口（H1-03）。
 *
 * 部门 × 岗位的编制与在职对照，缺口排序在前；可直接改编制数。
 */
class HeadcountController extends Controller
{
    public function index(Request $request): View
    {
        $deptId = $request->query('dept');
        $onlyGap = $request->boolean('gap');

        $rows = Headcount::query()
            ->with(['department:id,code,name', 'position:id,code,name,grade'])
            ->when($deptId, fn ($q) => $q->where('department_id', (int) $deptId))
            ->get()
            ->map(function (Headcount $h) {
                $filled = Employee::query()
                    ->where('department_id', $h->department_id)
                    ->where('position_id', $h->position_id)
                    ->where('status', '!=', 'left')
                    ->count();

                $h->filled = $filled;

                return $h;
            })
            ->when($onlyGap, fn ($c) => $c->filter(fn (Headcount $h) => $h->planned > $h->filled))
            ->sortByDesc(fn (Headcount $h) => $h->planned - $h->filled);

        $kpi = [
            'rows' => $rows->count(),
            'planned' => (int) $rows->sum('planned'),
            'filled' => (int) $rows->sum('filled'),
            'gap' => (int) $rows->sum(fn (Headcount $h) => max(0, $h->planned - $h->filled)),
        ];

        return view('hr::org.headcounts.index', [
            'rows' => $rows,
            'kpi' => $kpi,
            'deptId' => $deptId,
            'onlyGap' => $onlyGap,
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']),
            'positions' => Position::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(Request $request, Headcount $headcount): RedirectResponse
    {
        $data = $request->validate([
            'planned' => ['required', 'integer', 'min:0', 'max:999'],
        ], [], ['planned' => '编制数']);

        $headcount->update(['planned' => (int) $data['planned']]);

        return redirect()->route('hr.org.headcounts.index')
            ->with('status', "{$headcount->department?->name} · {$headcount->position?->name} 编制改为 {$data['planned']}。");
    }

    /** 新建一条编制（给部门新增岗位编制时用） */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:hr.departments,id'],
            'position_id' => ['required', 'integer', 'exists:hr.positions,id'],
            'planned' => ['required', 'integer', 'min:0', 'max:999'],
        ], [], [
            'department_id' => '部门',
            'position_id' => '岗位',
            'planned' => '编制数',
        ]);

        $exists = Headcount::query()
            ->where('department_id', $data['department_id'])
            ->where('position_id', $data['position_id'])
            ->exists();

        if ($exists) {
            return back()->with('error', '这个部门与岗位的编制已经存在，直接改数字即可。');
        }

        Headcount::query()->create([
            'department_id' => $data['department_id'],
            'position_id' => $data['position_id'],
            'planned' => (int) $data['planned'],
            'filled' => 0,
        ]);

        return redirect()->route('hr.org.headcounts.index')->with('status', '编制已新增。');
    }
}
