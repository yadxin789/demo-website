@extends('hr::layouts.app')

@section('title', '绩效评分 · ' . $employee->name)
@section('sub', '华瑞集团（控股）· ' . $cycle->period . ' 周期，五项指标的上级评分')

@section('content')
    <div class="hr-card mb-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h3 style="margin:0 0 4px">{{ $employee->name }}
                    <span style="font-size:12px; font-family:ui-monospace,Consolas,monospace; color:#9a9aad">{{ $employee->employee_no }}</span>
                </h3>
                <div style="color:#6f6f80; font-size:13px">
                    {{ $employee->department?->name ?? '—' }} ｜ {{ $employee->position?->name ?? '—' }}
                    ｜ {{ $cycle->name }}（{{ $cycle->period }}）
                    @if ($cycle->status === 'closed')
                        <span class="hr-badge b-done">已结束</span>
                    @else
                        <span class="hr-badge b-progress">进行中</span>
                    @endif
                </div>
            </div>
            <div class="text-end">
                <div style="color:#9a9aad; font-size:12px">当前加权总分</div>
                <div style="font-size:26px; font-weight:700; color:#5f3dc4">{{ $summary['final_score'] ?? '—' }}</div>
                <div style="font-size:12px; color:#6f6f80">{{ $summary['grade'] ?? '还没评完' }}</div>
            </div>
        </div>
    </div>

    <form method="post" action="{{ route('hr.performance.reviews.submit', ['employee' => $employee->id, 'cycle' => $cycle->id]) }}">
        @csrf
        <div class="hr-card">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 style="margin:0">指标评分</h3>
                <span style="font-size:12px; color:#6f6f80">权重合计 {{ $weightTotal }}%</span>
            </div>

            <table class="hr-table">
                <thead>
                <tr><th>指标</th><th class="num">权重</th><th>目标值</th><th class="num">自评</th><th class="num" style="width:130px">上级评分</th></tr>
                </thead>
                <tbody>
                @forelse ($indicators as $ind)
                    @php($row = $rows[$ind->id] ?? null)
                    <tr>
                        <td><b>{{ $ind->name }}</b></td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $ind->weight, 2), '0'), '.') }}%</td>
                        <td style="font-size:12px; color:#6f6f80">{{ $ind->target ?? '—' }}</td>
                        <td class="num">{{ $row?->self_score ?? '—' }}</td>
                        <td class="num">
                            <input class="form-control form-control-sm text-end" name="scores[{{ $ind->id }}]"
                                   type="number" min="0" max="100" step="0.5"
                                   value="{{ $row?->manager_score !== null ? (float) $row->manager_score : '' }}"
                                   @disabled($cycle->status === 'closed')>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="hr-empty">这个周期还没有指标，先去"绩效周期"页配指标。</td></tr>
                @endforelse
                </tbody>
            </table>

            <div class="d-flex gap-2 mt-3">
                @if ($cycle->status !== 'closed' && $indicators->isNotEmpty())
                    <button class="btn btn-primary" type="submit">保存评分</button>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('hr.performance.reviews', ['cycle' => $cycle->id]) }}">返回列表</a>
            </div>
        </div>
    </form>

    <div class="hr-card mt-3">
        <h3>算法说明</h3>
        <div style="font-size:13px; color:#6f6f80; line-height:1.9">
            加权总分 = Σ（指标权重 × 上级评分）÷ 100。<br>
            例：工作质量 30% × 88 分 + 工作效率 25% × 82 分 + 协作沟通 20% × 90 分 + 专业能力 15% × 85 分 + 纪律出勤 10% × 95 分 = 87.75 分（良好）。<br>
            五项都有上级分才算"完成"，结果页只统计已完成的人。
        </div>
    </div>
@endsection
