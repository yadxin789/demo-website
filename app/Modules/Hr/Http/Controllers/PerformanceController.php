<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\PerformanceCycle;
use App\Modules\Hr\Models\PerformanceIndicator;
use App\Modules\Hr\Models\PerformanceReview;
use App\Modules\Hr\Services\PerformanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * 绩效（H4-05 ~ H4-08）：周期与指标、评分录入、结果分布。
 *
 * 指标权重合计不强制等于 100，但页面上要显性提示（演示时客户会问"权重怎么配"）。
 */
class PerformanceController extends Controller
{
    public function __construct(private readonly PerformanceService $performance)
    {
    }

    // ---------- 周期 + 指标 ----------
    public function cycles(Request $request): View
    {
        $cycles = PerformanceCycle::query()->orderByDesc('period')->get();
        $cycleId = (int) $request->query('cycle', 0);
        $current = $cycles->firstWhere('id', $cycleId) ?? $cycles->first();

        $indicators = $current
            ? PerformanceIndicator::query()->where('cycle_id', $current->id)->orderBy('id')->get()
            : collect();

        $reviewStats = $current ? $this->reviewStats($current) : ['done' => 0, 'total' => 0];

        return view('hr::performance.cycles', [
            'cycles' => $cycles,
            'current' => $current,
            'currentId' => (int) ($current->id ?? 0),
            'indicators' => $indicators,
            'weightTotal' => $current ? $this->performance->weightTotal($current) : 0.0,
            'statusText' => ['in_progress' => '进行中', 'closed' => '已结束', 'draft' => '草稿'],
            'reviewStats' => $reviewStats,
            'indicatorStats' => $current ? $this->indicatorStats($current) : collect(),
        ]);
    }

    public function cycleStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30'],
            'period' => ['required', 'string', 'max:10'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ], [], [
            'name' => '周期名称',
            'period' => '周期编码',
            'start_date' => '开始日期',
            'end_date' => '结束日期',
        ]);

        $exists = PerformanceCycle::query()->where('period', $data['period'])->exists();
        if ($exists) {
            return back()->with('error', "周期编码 {$data['period']} 已存在，换一个（例如 2027Q1）。")->withInput();
        }

        $cycle = PerformanceCycle::query()->create($data + ['status' => 'in_progress']);

        return redirect()->route('hr.performance.cycles', ['cycle' => $cycle->id])
            ->with('status', "周期「{$cycle->name}」已创建，接下来加指标。");
    }

    public function indicatorStore(Request $request, PerformanceCycle $cycle): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20'],
            'weight' => ['required', 'numeric', 'min:1', 'max:100'],
            'target' => ['nullable', 'string', 'max:80'],
            'scope' => ['nullable', 'string', 'max:20'],
        ], [], [
            'name' => '指标名称',
            'weight' => '权重',
            'target' => '目标值',
        ]);

        if ($cycle->status === 'closed') {
            return back()->with('error', '周期已结束，不能再加指标。');
        }

        $currentTotal = $this->performance->weightTotal($cycle);
        $nextTotal = round($currentTotal + (float) $data['weight'], 2);

        if ($nextTotal > 100) {
            return back()->with('error', "权重会超 100%：现有 {$currentTotal}% ＋ 新增 {$data['weight']}% ＝ {$nextTotal}%。先下调其它指标。");
        }

        PerformanceIndicator::query()->create([
            'cycle_id' => $cycle->id,
            'name' => $data['name'],
            'weight' => $data['weight'],
            'target' => $data['target'] ?? null,
            'scope' => $data['scope'] ?? '通用',
        ]);

        return redirect()->route('hr.performance.cycles', ['cycle' => $cycle->id])
            ->with('status', "指标「{$data['name']}」已加，权重合计 {$nextTotal}%。");
    }

    public function cycleClose(PerformanceCycle $cycle): RedirectResponse
    {
        if ($cycle->status === 'closed') {
            return back()->with('error', '这个周期已经结束了。');
        }

        $stats = $this->reviewStats($cycle);
        $cycle->update(['status' => 'closed']);

        $tail = $stats['total'] - $stats['done'] > 0
            ? '（还有 ' . ($stats['total'] - $stats['done']) . ' 人未评完，结果页只统计已完成的人）'
            : '';

        return redirect()->route('hr.performance.cycles', ['cycle' => $cycle->id])
            ->with('status', "周期「{$cycle->name}」已结束{$tail}。");
    }

    // ---------- 评分 ----------
    public function reviews(Request $request): View
    {
        $cycle = $this->resolveCycle($request);
        $deptId = (int) $request->query('dept', 0);
        $q = trim((string) $request->query('q', ''));
        $done = (string) $request->query('done', '');

        $summaries = $cycle
            ? $this->performance->employeeSummaries($cycle, $deptId > 0 ? $deptId : null, $q !== '' ? $q : null)
            : [];

        if ($done === '1') {
            $summaries = array_values(array_filter($summaries, fn ($s) => $s['done'] === true));
        } elseif ($done === '0') {
            $summaries = array_values(array_filter($summaries, fn ($s) => $s['done'] === false));
        }

        $stats = $this->performance->stats($this->performance->employeeSummaries($cycle, $deptId > 0 ? $deptId : null, null));

        return view('hr::performance.reviews', [
            'cycle' => $cycle,
            'cycles' => PerformanceCycle::query()->orderByDesc('period')->get(),
            'summaries' => array_slice($summaries, 0, 200),
            'total' => count($summaries),
            'stats' => $stats,
            'deptId' => $deptId,
            'q' => $q,
            'done' => $done,
            'departments' => $this->performance->departments(),
        ]);
    }

    public function reviewForm(Request $request, Employee $employee): View
    {
        $cycle = $this->resolveCycle($request);

        if (! $cycle) {
            abort(404);
        }

        $indicators = PerformanceIndicator::query()
            ->where('cycle_id', $cycle->id)
            ->orderBy('id')
            ->get();

        $rows = PerformanceReview::query()
            ->where('cycle_id', $cycle->id)
            ->where('employee_id', $employee->id)
            ->get()
            ->keyBy('indicator_id');

        $summary = collect($this->performance->employeeSummaries($cycle))
            ->firstWhere('employee_id', $employee->id);

        return view('hr::performance.review-form', [
            'cycle' => $cycle,
            'employee' => $employee->load(['department:id,name', 'position:id,name']),
            'indicators' => $indicators,
            'rows' => $rows,
            'summary' => $summary,
            'weightTotal' => $this->performance->weightTotal($cycle),
        ]);
    }

    public function reviewSubmit(Request $request, Employee $employee): RedirectResponse
    {
        $cycle = $this->resolveCycle($request);

        if (! $cycle) {
            return back()->with('error', '没找到对应的绩效周期。');
        }

        if ($cycle->status === 'closed') {
            return back()->with('error', '周期已结束，评分不能修改。');
        }

        $scores = (array) $request->input('scores', []);
        $scores = array_map(fn ($v) => is_numeric($v) ? (float) $v : null, $scores);

        $result = $this->performance->submitManagerScores($cycle, $employee, $scores);

        if ($result['updated'] === 0) {
            return back()->with('error', '一个分数都没填，先把上级评分补上。');
        }

        $tail = $result['done']
            ? '，该员工已完成，加权总分 ' . number_format((float) $result['total'], 2) . ' 分'
            : '，还有指标没评完';

        return redirect()->route('hr.performance.reviews', ['cycle' => $cycle->id])
            ->with('status', "{$employee->name} 已保存 {$result['updated']} 项评分{$tail}。");
    }

    // ---------- 结果 ----------
    public function results(Request $request): View
    {
        $cycle = $this->resolveCycle($request, preferClosed: true);

        $summaries = $cycle ? $this->performance->employeeSummaries($cycle) : [];
        $stats = $this->performance->stats($summaries);

        $ranked = array_values(array_filter($summaries, fn ($s) => $s['done'] === true));
        usort($ranked, fn ($a, $b) => $b['final_score'] <=> $a['final_score']);

        return view('hr::performance.results', [
            'cycle' => $cycle,
            'cycles' => PerformanceCycle::query()->orderByDesc('period')->get(),
            'stats' => $stats,
            'top' => array_slice($ranked, 0, 10),
            'bottom' => array_slice(array_reverse($ranked), 0, 5),
            'depts' => $this->performance->byDepartment($summaries),
            'pending' => array_slice(array_values(array_filter($summaries, fn ($s) => $s['done'] === false)), 0, 8),
        ]);
    }

    /** 取周期：query cycle > （结果页优先已结束的周期）> 最新周期 */
    private function resolveCycle(Request $request, bool $preferClosed = false): ?PerformanceCycle
    {
        $id = (int) $request->query('cycle', 0);

        if ($id > 0) {
            return PerformanceCycle::query()->find($id);
        }

        if ($preferClosed) {
            $closed = PerformanceCycle::query()->where('status', 'closed')->orderByDesc('period')->first();
            if ($closed !== null) {
                return $closed;
            }
        }

        return PerformanceCycle::query()->orderByDesc('period')->first();
    }

    /** @return array{done:int, total:int} */
    private function reviewStats(PerformanceCycle $cycle): array
    {
        $stats = $this->performance->stats($this->performance->employeeSummaries($cycle));

        return ['done' => $stats['done'], 'total' => $stats['total']];
    }

    /** 每个指标的平均上级分（周期页展示用） */
    private function indicatorStats(PerformanceCycle $cycle): Collection
    {
        return PerformanceReview::query()
            ->where('cycle_id', $cycle->id)
            ->whereNotNull('manager_score')
            ->selectRaw('indicator_id, round(avg(manager_score), 2) as avg_score, count(*) as rated')
            ->groupBy('indicator_id')
            ->get()
            ->keyBy('indicator_id');
    }
}
