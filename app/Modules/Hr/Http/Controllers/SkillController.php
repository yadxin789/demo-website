<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Certificate;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\EmployeeSkill;
use App\Modules\Hr\Models\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 技能矩阵与证书（HR，M7 增补）。
 *
 * 对标 OrangeHRM Qualifications：技能（1–5 级，员工 × 技能矩阵）+ 证书（含到期提醒）。
 */
class SkillController extends Controller
{
    public function index(Request $request): View
    {
        $dept = (string) $request->query('dept', '');

        $skills = Skill::query()->orderBy('code')->get();

        $employees = Employee::query()
            ->with('department:id,name')
            ->with('skills.skill:id,code,name')
            ->where('status', 'active')
            ->when($dept !== '', fn ($q) => $q->where('department_id', (int) $dept))
            ->orderBy('employee_no')
            ->limit(30)
            ->get();

        $departments = Department::query()->orderBy('name')->get(['id', 'name']);

        // 每个技能的平均等级，用来判断技能短板
        $skillAvg = EmployeeSkill::query()
            ->selectRaw('skill_id, AVG(level) as avg_level, COUNT(*) as holders')
            ->groupBy('skill_id')
            ->get()
            ->keyBy('skill_id');

        return view('hr::skills.index', [
            'skills' => $skills,
            'employees' => $employees,
            'departments' => $departments,
            'dept' => $dept,
            'skillAvg' => $skillAvg,
            'stat' => [
                'skills' => $skills->count(),
                'holders' => EmployeeSkill::query()->count(),
                'avg_level' => round((float) EmployeeSkill::query()->avg('level'), 2),
                'certificates' => Certificate::query()->count(),
            ],
        ]);
    }

    public function storeSkill(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:24', 'unique:hr.skills,code'],
            'name' => ['required', 'string', 'max:64'],
            'category' => ['required', 'string', 'max:32'],
            'max_level' => ['nullable', 'integer', 'min:3', 'max:9'],
            'description' => ['nullable', 'string', 'max:300'],
        ], [], ['code' => '技能编号', 'name' => '技能名称', 'category' => '类别', 'max_level' => '最高等级']);

        Skill::query()->create($data + ['max_level' => (int) ($data['max_level'] ?? 5)]);

        return redirect()->route('hr.skills.index')->with('status', "技能 {$data['code']} 已建。");
    }

    public function updateSkill(Request $request, Skill $skill): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'category' => ['required', 'string', 'max:32'],
            'max_level' => ['nullable', 'integer', 'min:3', 'max:9'],
            'description' => ['nullable', 'string', 'max:300'],
        ], [], ['name' => '技能名称', 'max_level' => '最高等级']);

        $skill->update($data + ['max_level' => (int) ($data['max_level'] ?? $skill->max_level)]);

        return redirect()->route('hr.skills.index')->with('status', "技能 {$skill->code} 已更新。");
    }

    /** 给员工挂一个技能等级（upsert） */
    public function assign(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr.employees,id'],
            'skill_id' => ['required', 'integer', 'exists:hr.skills,id'],
            'level' => ['required', 'integer', 'min:1', 'max:9'],
            'remark' => ['nullable', 'string', 'max:200'],
        ], [], ['employee_id' => '员工', 'skill_id' => '技能', 'level' => '等级']);

        $skill = Skill::query()->find((int) $data['skill_id']);
        $level = min((int) $data['level'], (int) ($skill->max_level ?? 5));

        EmployeeSkill::query()->updateOrCreate(
            ['employee_id' => (int) $data['employee_id'], 'skill_id' => (int) $data['skill_id']],
            ['level' => $level, 'assessed_on' => now()->toDateString(), 'remark' => $data['remark'] ?? null]
        );

        return redirect()->route('hr.skills.index')
            ->with('status', "已记录 {$skill?->name} 等级 {$level}。");
    }

    public function destroyAssignment(EmployeeSkill $assignment): RedirectResponse
    {
        $assignment->delete();

        return redirect()->route('hr.skills.index')->with('status', '该技能记录已删除。');
    }

    public function certificates(Request $request): View
    {
        $expiring = (int) $request->query('expiring', 0);

        $certs = Certificate::query()
            ->with('employee:id,employee_no,name,department_id', 'employee.department:id,name')
            ->when($expiring === 1, fn ($q) => $q->whereNotNull('expire_on')->whereDate('expire_on', '<=', now()->addDays(60)->toDateString()))
            ->orderByRaw('expire_on is null')
            ->orderBy('expire_on')
            ->limit(50)
            ->get();

        return view('hr::skills.certificates', [
            'certs' => $certs,
            'expiring' => $expiring,
            'employees' => Employee::query()->where('status', 'active')->orderBy('employee_no')->get(['id', 'employee_no', 'name']),
            'stat' => [
                'total' => Certificate::query()->count(),
                'within60' => Certificate::query()->whereNotNull('expire_on')->whereDate('expire_on', '<=', now()->addDays(60)->toDateString())->whereDate('expire_on', '>=', now()->toDateString())->count(),
                'expired' => Certificate::query()->whereNotNull('expire_on')->whereDate('expire_on', '<', now()->toDateString())->count(),
            ],
        ]);
    }

    public function storeCertificate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr.employees,id'],
            'name' => ['required', 'string', 'max:96'],
            'cert_no' => ['nullable', 'string', 'max:64'],
            'issuer' => ['nullable', 'string', 'max:64'],
            'issued_on' => ['nullable', 'date'],
            'expire_on' => ['nullable', 'date', 'after_or_equal:issued_on'],
            'remark' => ['nullable', 'string', 'max:200'],
        ], [], ['employee_id' => '员工', 'name' => '证书名称', 'cert_no' => '证书编号', 'issuer' => '发证机构', 'issued_on' => '发证日期', 'expire_on' => '到期日期']);

        Certificate::query()->create($data);

        return redirect()->route('hr.skills.certificates')->with('status', "证书「{$data['name']}」已登记。");
    }

    public function destroyCertificate(Certificate $certificate): RedirectResponse
    {
        $certificate->delete();

        return redirect()->route('hr.skills.certificates')->with('status', '证书记录已删除。');
    }
}
