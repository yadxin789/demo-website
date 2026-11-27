@extends('hr::layouts.app')

@section('title', '绩效结果')
@section('sub', '分布、均分、部门对比、排名')

@section('content')
    @if (! $cycle)
        <div class="hr-card"><div class="hr-empty">还没有绩效周期，先去"绩效周期"页建一个。</div></div>
    @else
        <div class="hr-card mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h3 style="margin:0 0 4px">{{ $cycle->name }}
                        <span class="hr-badge {{ $cycle->status === 'closed' ? 'b-done' : 'b-progress' }}">
                            {{ $cycle->status === 'closed' ? '已结束' : '进行中' }}
                        </span>
                    </h3>
                    <div style="color:#6f6f80; font-size:13px">{{ $cycle->period }} · 已统计 {{ $stats['done'] }} 人</div>
                </div>
                <form class="d-flex gap-2" method="get" action="{{ route('hr.performance.results') }}" style="margin:0">
                    <select class="form-select form-select-sm" name="cycle" onchange="this.form.submit()" style="width:220px">
                        @foreach ($cycles as $c)
                            <option value="{{ $c->id }}" @selected($c->id === $cycle->id)>{{ $c->period }} · {{ $c->name }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>

        <div class="hr-kpis mb-3">
            <div class="hr-kpi purple">
                <div class="lbl">平均分</div>
                <div class="num">{{ $stats['avg'] ?? '—' }}</div>
                <div class="hint">中位 {{ $stats['median'] ?? '—' }}</div>
            </div>
            <div class="hr-kpi ok">
                <div class="lbl">最高</div>
                <div class="num">{{ $stats['max'] ?? '—' }}</div>
                <div class="hint">最低 {{ $stats['min'] ?? '—' }}</div>
            </div>
            <div class="hr-kpi">
                <div class="lbl">完成率</div>
                <div class="num">{{ $stats['rate'] }}%</div>
                <div class="hint">{{ $stats['done'] }} / {{ $stats['total'] }} 人</div>
            </div>
            <div class="hr-kpi warn">
                <div class="lbl">待评</div>
                <div class="num">{{ $stats['pending'] }}</div>
                <div class="hint">补完分才会进统计</div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="hr-card">
                    <h3>等级分布</h3>
                    @php($colors = ['优秀' => '#5f3dc4', '良好' => '#0ca678', '合格' => '#4c6ef5', '待改进' => '#d6336c'])
                    @php($maxBucket = max(1, max($stats['buckets'])))
                    <div style="margin-top:12px; display:flex; flex-direction:column; gap:10px">
                        @foreach ($stats['buckets'] as $grade => $n)
                            <div style="display:grid; grid-template-columns:64px 1fr 46px; align-items:center; gap:10px">
                                <span style="font-size:13px">{{ $grade }}</span>
                                <div style="height:14px; background:#eceaf6; border-radius:7px; overflow:hidden">
                                    <span style="display:block; height:14px; background:{{ $colors[$grade] }}; width: {{ (int) round($n / $maxBucket * 100) }}%"></span>
                                </div>
                                <b style="font-size:13px; text-align:right">{{ $n }} 人</b>
                            </div>
                        @endforeach
                    </div>
                    <div style="font-size:12px; color:#9a9aad; margin-top:10px">
                        口径：优秀 ≥90 / 良好 80~89 / 合格 70~79 / 待改进 &lt;70。
                    </div>
                </div>

                <div class="hr-card mt-3">
                    <h3>部门平均（按已完成人数）</h3>
                    <table class="hr-table">
                        <thead><tr><th>部门</th><th class="num">人数</th><th class="num">平均分</th></tr></thead>
                        <tbody>
                        @forelse ($depts as $d)
                            <tr>
                                <td>{{ $d['department'] }}</td>
                                <td class="num">{{ $d['people'] }}</td>
                                <td class="num" style="font-weight:600">{{ $d['avg'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="hr-empty">还没有完成评分的员工。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="hr-card">
                    <h3>前 10 名</h3>
                    <table class="hr-table">
                        <thead><tr><th class="num">#</th><th>姓名</th><th>部门</th><th class="num">总分</th></tr></thead>
                        <tbody>
                        @forelse ($top as $i => $t)
                            <tr>
                                <td class="num">{{ $i + 1 }}</td>
                                <td><b>{{ $t['name'] }}</b></td>
                                <td style="font-size:12px">{{ $t['department'] }}</td>
                                <td class="num" style="font-weight:600">{{ $t['final_score'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="hr-empty">还没有完成评分的人。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="hr-card mt-3">
                    <h3>需关注（后 5 名）</h3>
                    <table class="hr-table">
                        <thead><tr><th>姓名</th><th>部门</th><th class="num">总分</th><th>等级</th></tr></thead>
                        <tbody>
                        @forelse ($bottom as $b)
                            <tr>
                                <td>{{ $b['name'] }}</td>
                                <td style="font-size:12px">{{ $b['department'] }}</td>
                                <td class="num">{{ $b['final_score'] }}</td>
                                <td><span class="hr-badge {{ $b['grade'] === '待改进' ? 'b-bad' : 'b-progress' }}">{{ $b['grade'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="hr-empty">还没有完成评分的人。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($pending !== [])
                    <div class="hr-card mt-3">
                        <h3>还没评完的人（前 {{ count($pending) }} 位）</h3>
                        <table class="hr-table">
                            <thead><tr><th>姓名</th><th class="num">进度</th><th></th></tr></thead>
                            <tbody>
                            @foreach ($pending as $p)
                                <tr>
                                    <td>{{ $p['name'] }}</td>
                                    <td class="num">{{ $p['done_count'] }}/{{ $p['indicator_count'] }}</td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-primary"
                                           href="{{ route('hr.performance.reviews.form', ['employee' => $p['employee_id'], 'cycle' => $cycle->id]) }}">补分</a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @endif
@endsection
