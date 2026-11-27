<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\TrainingCourse;
use App\Modules\Hr\Models\TrainingRecord;
use App\Modules\Hr\Models\TrainingSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 培训管理（HR，M7 增补）。
 *
 * 对标 OrangeHRM Training：课程（课程库）→ 场次（开班）→ 记录（报名与结果）。
 */
class TrainingController extends Controller
{
    public function index(Request $request): View
    {
        $courses = TrainingCourse::query()->withCount('sessions')->orderBy('code')->get();

        $sessions = TrainingSession::query()
            ->with('course:id,code,title,hours')
            ->withCount(['records as enrolled', 'records as passed' => fn ($q) => $q->where('result', 'pass')])
            ->orderByDesc('start_date')
            ->limit(20)
            ->get();

        $records = TrainingRecord::query()
            ->with(['employee:id,employee_no,name', 'session:id,session_no,course_id', 'session.course:id,code,title'])
            ->latest('id')
            ->limit(30)
            ->get();

        $hoursByCategory = TrainingCourse::query()
            ->selectRaw('category, SUM(hours) as total')
            ->groupBy('category')
            ->get();

        return view('hr::training.index', [
            'courses' => $courses,
            'sessions' => $sessions,
            'records' => $records,
            'employees' => Employee::query()->where('status', 'active')->orderBy('employee_no')->get(['id', 'employee_no', 'name']),
            'hoursByCategory' => $hoursByCategory,
            'stat' => [
                'courses' => $courses->count(),
                'sessions' => $sessions->count(),
                'enrolled' => TrainingRecord::query()->count(),
                'pass_rate' => $this->passRate(),
            ],
        ]);
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:24', 'unique:hr.training_courses,code'],
            'title' => ['required', 'string', 'max:96'],
            'category' => ['required', 'string', 'max:32'],
            'hours' => ['required', 'integer', 'min:1', 'max:500'],
            'provider' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [], ['code' => '课程编号', 'title' => '课程名称', 'category' => '类别', 'hours' => '课时', 'provider' => '讲师/机构']);

        TrainingCourse::query()->create($data + ['is_active' => true]);

        return redirect()->route('hr.training.index')->with('status', "课程 {$data['code']} 已建。");
    }

    public function updateCourse(Request $request, TrainingCourse $course): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:96'],
            'category' => ['required', 'string', 'max:32'],
            'hours' => ['required', 'integer', 'min:1', 'max:500'],
            'provider' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ], [], ['title' => '课程名称', 'hours' => '课时']);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $course->update($data);

        return redirect()->route('hr.training.index')->with('status', "课程 {$course->code} 已更新。");
    }

    public function storeSession(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:hr.training_courses,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'location' => ['nullable', 'string', 'max:64'],
            'trainer' => ['nullable', 'string', 'max:64'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ], [], ['course_id' => '课程', 'start_date' => '开班日期', 'end_date' => '结束日期', 'location' => '地点', 'trainer' => '讲师', 'capacity' => '名额']);

        $session = TrainingSession::query()->create([
            'course_id' => (int) $data['course_id'],
            'session_no' => $this->nextSessionNo(),
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'] ?? $data['start_date'],
            'location' => $data['location'] ?? null,
            'trainer' => $data['trainer'] ?? null,
            'capacity' => (int) ($data['capacity'] ?? 30),
            'status' => 'planned',
        ]);

        return redirect()->route('hr.training.index')->with('status', "开班 {$session->session_no} 已创建。");
    }

    public function storeRecord(Request $request, TrainingSession $session): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr.employees,id'],
            'result' => ['required', 'in:pending,pass,fail'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], [], ['employee_id' => '员工', 'result' => '结果', 'score' => '成绩']);

        $record = TrainingRecord::query()->updateOrCreate(
            ['session_id' => $session->id, 'employee_id' => (int) $data['employee_id']],
            [
                'result' => $data['result'],
                'score' => $data['score'] ?? null,
                'completed_at' => $data['result'] === 'pending' ? null : now(),
            ]
        );

        return redirect()->route('hr.training.index')
            ->with('status', "已登记 {$record->employee?->name} 的培训结果（{$data['result']}）。");
    }

    public function updateRecord(Request $request, TrainingRecord $record): RedirectResponse
    {
        $data = $request->validate([
            'result' => ['required', 'in:pending,pass,fail'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'remark' => ['nullable', 'string', 'max:200'],
        ], [], ['result' => '结果', 'score' => '成绩']);

        $record->update([
            'result' => $data['result'],
            'score' => $data['score'] ?? null,
            'completed_at' => $data['result'] === 'pending' ? null : now(),
            'remark' => $data['remark'] ?? $record->remark,
        ]);

        return redirect()->route('hr.training.index')->with('status', '培训记录已更新。');
    }

    private function passRate(): float
    {
        $judged = TrainingRecord::query()->whereIn('result', ['pass', 'fail'])->count();
        if ($judged === 0) {
            return 0.0;
        }

        return round(TrainingRecord::query()->where('result', 'pass')->count() / $judged * 100, 1);
    }

    private function nextSessionNo(): string
    {
        $prefix = 'TS-' . now()->format('Y') . '-';
        $last = TrainingSession::query()->where('session_no', 'like', $prefix . '%')->orderByDesc('session_no')->value('session_no');
        $seq = $last ? ((int) substr((string) $last, -3)) + 1 : 1;

        return $prefix . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
    }
}
