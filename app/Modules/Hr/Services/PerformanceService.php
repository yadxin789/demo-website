<?php

declare(strict_types=1);

namespace App\Modules\Hr\Services;

use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\PerformanceCycle;
use App\Modules\Hr\Models\PerformanceIndicator;
use App\Modules\Hr\Models\PerformanceReview;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * 绩效核算（H4-07 / H4-08）。
 *
 * 口径：
 * - 指标行 final_score = 该指标最终得分（上级分优先，没评则空）
 * - 员工加权总分 = Σ(指标权重 × 指标最终得分) / 100，只有 5 个指标都评完才算"完成"
 * - 分布四档：优秀 ≥90 / 良好 80~89 / 合格 70~79 / 待改进 <70
 */
class PerformanceService
{
    /** 完成状态：五个指标都有上级分 */
    public function isEmployeeDone(Collection $reviews): bool
    {
        return $reviews->every(fn ($r) => $r->manager_score !== null);
    }

    /**
     * 员工维度汇总（绩效评分页、结果页共用）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function employeeSummaries(PerformanceCycle $cycle, ?int $departmentId = null, ?string $q = null): array
    {
        $indicators = PerformanceIndicator::query()
            ->where('cycle_id', $cycle->id)
            ->orderBy('id')
            ->get(['id', 'name', 'weight']);

        $weightOf = $indicators->pluck('weight', 'id')->map(fn ($w) => (float) $w)->all();
        $indicatorCount = $indicators->count();

        $reviews = PerformanceReview::query()
            ->where('cycle_id', $cycle->id)
            ->get(['employee_id', 'indicator_id', 'self_score', 'manager_score', 'final_score'])
            ->groupBy('employee_id');

        $employees = Employee::query()
            ->with(['department:id,name', 'position:id,name'])
            // 参与考核的是当期在册人员：在职 + 试用；离职的不进绩效名册
            ->whereIn('status', ['active', 'probation'])
            ->when($departmentId, fn ($query) => $query->where('department_id', $departmentId))
            ->when($q !== null && $q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('name', 'like', "%{$q}%")->orWhere('employee_no', 'like', "%{$q}%");
            }))
            ->orderBy('employee_no')
            ->get(['id', 'employee_no', 'name', 'department_id', 'position_id', 'status']);

        $out = [];
        foreach ($employees as $e) {
            $rows = $reviews->get($e->id) ?? collect();

            $doneCount = $rows->filter(fn ($r) => $r->manager_score !== null)->count();
            $done = $indicatorCount > 0 && $doneCount === $indicatorCount;

            $finalScore = 0.0;
            $selfScore = 0.0;
            foreach ($rows as $r) {
                $w = $weightOf[$r->indicator_id] ?? 0.0;
                $finalScore += $w * (float) ($r->final_score ?? $r->manager_score ?? 0);
                $selfScore += $w * (float) ($r->self_score ?? 0);
            }

            $out[] = [
                'employee_id' => $e->id,
                'employee_no' => $e->employee_no,
                'name' => $e->name,
                'department' => $e->department?->name ?? '—',
                'position' => $e->position?->name ?? '—',
                'status' => $e->status,
                'has_rows' => $rows->count() > 0,
                'done_count' => $doneCount,
                'indicator_count' => $indicatorCount,
                'done' => $done,
                'self_score' => $done ? round($selfScore / 100, 2) : null,
                'final_score' => $done ? round($finalScore / 100, 2) : null,
                'grade' => $done ? $this->grade((float) ($finalScore / 100)) : null,
            ];
        }

        return $out;
    }

    /** 四档等级 */
    public function grade(float $score): string
    {
        return match (true) {
            $score >= 90 => '优秀',
            $score >= 80 => '良好',
            $score >= 70 => '合格',
            default => '待改进',
        };
    }

    /**
     * 结果页统计：分布、平均、最高最低、完成率。
     *
     * @param  array<int, array<string, mixed>>  $summaries
     * @return array<string, mixed>
     */
    public function stats(array $summaries): array
    {
        // 只统计"本期实际参与考核"的人（有评分记录的行）；完全没记录的人不算进完成率，
        // 否则新建周期时会显示成 0% 完成，看起来像系统坏了。
        $eligible = array_values(array_filter($summaries, fn ($s) => ($s['has_rows'] ?? false) === true));

        $done = array_values(array_filter($eligible, fn ($s) => $s['done'] === true));

        $buckets = ['优秀' => 0, '良好' => 0, '合格' => 0, '待改进' => 0];
        foreach ($done as $s) {
            $buckets[$s['grade']]++;
        }

        $scores = array_map(fn ($s) => (float) $s['final_score'], $done);
        sort($scores);

        $total = count($eligible);

        return [
            'total' => $total,
            'done' => count($done),
            'pending' => $total - count($done),
            'rate' => $total > 0 ? round(count($done) / $total * 100, 1) : 0.0,
            'avg' => $scores !== [] ? round(array_sum($scores) / count($scores), 2) : null,
            'min' => $scores !== [] ? $scores[0] : null,
            'max' => $scores !== [] ? $scores[count($scores) - 1] : null,
            'buckets' => $buckets,
            'median' => $scores !== [] ? $scores[(int) floor((count($scores) - 1) / 2)] : null,
        ];
    }

    /**
     * 部门平均（只统计已完成员工）。
     *
     * @param  array<int, array<string, mixed>>  $summaries
     * @return array<int, array<string, mixed>>
     */
    public function byDepartment(array $summaries): array
    {
        $done = array_filter($summaries, fn ($s) => $s['done'] === true);

        $grouped = [];
        foreach ($done as $s) {
            $key = $s['department'];
            $grouped[$key] ??= ['department' => $key, 'people' => 0, 'sum' => 0.0];
            $grouped[$key]['people']++;
            $grouped[$key]['sum'] += (float) $s['final_score'];
        }

        $out = [];
        foreach ($grouped as $g) {
            $out[] = [
                'department' => $g['department'],
                'people' => $g['people'],
                'avg' => round($g['sum'] / $g['people'], 2),
            ];
        }

        usort($out, fn ($a, $b) => $b['avg'] <=> $a['avg']);

        return $out;
    }

    /**
     * 提交上级评分：按指标批量写 manager_score，并同步 final_score。
     *
     * @param  array<int, float|int|string|null>  $scores  指标 id => 上级分
     * @return array{updated:int, done:bool, total:float|null}
     */
    public function submitManagerScores(PerformanceCycle $cycle, Employee $employee, array $scores): array
    {
        $indicators = PerformanceIndicator::query()
            ->where('cycle_id', $cycle->id)
            ->get(['id', 'name', 'weight']);

        if ($indicators->isEmpty()) {
            throw new RuntimeException('该周期还没有绩效指标，先在"绩效周期"页加上指标。');
        }

        $updated = 0;
        $weighted = 0.0;
        $allDone = true;

        foreach ($indicators as $ind) {
            $raw = $scores[$ind->id] ?? null;

            /** @var PerformanceReview $review */
            $review = PerformanceReview::query()->firstOrNew([
                'cycle_id' => $cycle->id,
                'employee_id' => $employee->id,
                'indicator_id' => $ind->id,
            ]);

            if ($raw === null || $raw === '') {
                if ($review->manager_score === null) {
                    $allDone = false;
                }

                continue;
            }

            $score = round(max(0, min(100, (float) $raw)), 2);
            $review->manager_score = $score;
            $review->final_score = $score;
            $review->self_score ??= $score;
            $review->save();

            $updated++;
        }

        // 重新按库里实际数据判断是否完成，并算加权总分
        $rows = PerformanceReview::query()
            ->where('cycle_id', $cycle->id)
            ->where('employee_id', $employee->id)
            ->get(['indicator_id', 'manager_score']);

        $weightOf = $indicators->pluck('weight', 'id')->map(fn ($w) => (float) $w)->all();
        $allDone = $rows->count() === $indicators->count() && $rows->every(fn ($r) => $r->manager_score !== null);

        if ($allDone) {
            foreach ($rows as $r) {
                $weighted += ($weightOf[$r->indicator_id] ?? 0.0) * (float) $r->manager_score;
            }
            $weighted = round($weighted / 100, 2);
        } else {
            $weighted = null;
        }

        return ['updated' => $updated, 'done' => $allDone, 'total' => $allDone ? $weighted : null];
    }

    /** 指标权重合计（用于"权重必须=100%"校验提示） */
    public function weightTotal(PerformanceCycle $cycle): float
    {
        return round((float) PerformanceIndicator::query()
            ->where('cycle_id', $cycle->id)
            ->sum('weight'), 2);
    }

    /** 部门下拉 */
    public function departments(): Collection
    {
        return Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']);
    }
}
