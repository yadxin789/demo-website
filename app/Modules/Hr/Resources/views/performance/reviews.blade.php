@extends('hr::layouts.app')

@section('title', '绩效评分')
@section('sub', '华瑞集团（控股）· 按员工逐项补上级评分，五项齐了才算完成')

@section('content')
    @if (! $cycle)
        <div class="hr-card"><div class="hr-empty">还没有绩效周期，先去"绩效周期"页建一个。</div></div>
    @else
        <div class="hr-kpis mb-3">
            <div class="hr-kpi purple">
                <div class="lbl">本期参与</div>
                <div class="num">{{ $stats['total'] }}</div>
                <div class="hint">{{ $cycle->period }} 有评分记录的人</div>
            </div>
            <div class="hr-kpi ok">
                <div class="lbl">已完成</div>
                <div class="num">{{ $stats['done'] }}</div>
                <div class="hint">五项指标都评完</div>
            </div>
            <div class="hr-kpi warn">
                <div class="lbl">待评</div>
                <div class="num">{{ $stats['pending'] }}</div>
                <div class="hint">点姓名进去补分</div>
            </div>
            <div class="hr-kpi">
                <div class="lbl">完成率</div>
                <div class="num">{{ $stats['rate'] }}%</div>
                <div class="hint">平均分 {{ $stats['avg'] ?? '—' }}</div>
            </div>
        </div>

        <div class="hr-card">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <h3 style="margin:0">{{ $cycle->name }} · 评分进度</h3>
                <div class="d-flex gap-2 flex-wrap">
                    <form class="d-flex gap-2" method="get" action="{{ route('hr.performance.reviews') }}" style="margin:0">
                        <input name="cycle" type="hidden" value="{{ $cycle->id }}">
                        <select class="form-select form-select-sm" name="dept" onchange="this.form.submit()" style="width:150px">
                            <option value="0">全部部门</option>
                            @foreach ($departments as $d)
                                <option value="{{ $d->id }}" @selected($deptId === $d->id)>{{ $d->name }}</option>
                            @endforeach
                        </select>
                        <select class="form-select form-select-sm" name="done" onchange="this.form.submit()" style="width:120px">
                            <option value="">全部</option>
                            <option value="0" @selected($done === '0')>只看待评</option>
                            <option value="1" @selected($done === '1')>只看完成</option>
                        </select>
                        <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="搜姓名/工号" style="width:140px">
                        <button class="btn btn-sm btn-outline-secondary" type="submit">筛</button>
                    </form>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.performance.results', ['cycle' => $cycle->id]) }}">看结果</a>
                </div>
            </div>

            <table class="hr-table">
                <thead>
                <tr><th>工号</th><th>姓名</th><th>部门</th><th>岗位</th><th class="num">指标进度</th><th class="num">自评加权</th><th class="num">上级加权</th><th>等级</th><th></th></tr>
                </thead>
                <tbody>
                @forelse ($summaries as $s)
                    <tr>
                        <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $s['employee_no'] }}</td>
                        <td><b>{{ $s['name'] }}</b></td>
                        <td>{{ $s['department'] }}</td>
                        <td style="font-size:12px">{{ $s['position'] }}</td>
                        <td class="num">
                            {{ $s['done_count'] }}/{{ $s['indicator_count'] }}
                            @if ($s['done'])
                                <span class="hr-badge b-done">完成</span>
                            @else
                                <span class="hr-badge b-draft">待评</span>
                            @endif
                        </td>
                        <td class="num">{{ $s['self_score'] ?? '—' }}</td>
                        <td class="num" style="font-weight:600">{{ $s['final_score'] ?? '—' }}</td>
                        <td>
                            @if ($s['grade'])
                                <span class="hr-badge {{ $s['grade'] === '优秀' ? 'b-done' : ($s['grade'] === '待改进' ? 'b-bad' : 'b-progress') }}">{{ $s['grade'] }}</span>
                            @else
                                <span style="color:#9a9aad; font-size:12px">—</span>
                            @endif
                        </td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary"
                               href="{{ route('hr.performance.reviews.form', ['employee' => $s['employee_id'], 'cycle' => $cycle->id]) }}">
                                评分
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="hr-empty">这个筛选条件下没有人。</td></tr>
                @endforelse
                </tbody>
            </table>
            @if ($total > count($summaries))
                <div style="color:#9a9aad; font-size:12px; margin-top:8px">
                    共 {{ $total }} 条，这里只列前 {{ count($summaries) }} 条；用上面的筛选收窄范围。
                </div>
            @endif
        </div>
    @endif
@endsection
