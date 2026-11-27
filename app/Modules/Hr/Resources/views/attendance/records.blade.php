@extends('hr::layouts.app')

@section('title', '打卡记录')
@section('sub', '{{ $month }} 期间，按人、按部门、按状态查')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi purple">
            <div class="lbl">打卡记录</div>
            <div class="num">{{ $kpi['total'] }}</div>
            <div class="hint">{{ $kpi['people'] }} 人 · {{ $month }}</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">正常率</div>
            <div class="num">{{ $kpi['normal_rate'] }}<span style="font-size:14px">%</span></div>
            <div class="hint">正常 {{ $kpi['normal'] }} 条</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">迟到</div>
            <div class="num">{{ $kpi['late'] }}</div>
            <div class="hint">累计 {{ $kpi['late_minutes'] }} 分钟</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">早退</div>
            <div class="num">{{ $kpi['early'] }}</div>
            <div class="hint">提前离岗</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">缺卡</div>
            <div class="num">{{ $kpi['missing'] }}</div>
            <div class="hint">待补卡</div>
        </div>
    </div>

    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-2">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">月份</label>
                <input class="form-control form-control-sm" name="month" type="month" value="{{ $month }}">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">部门</label>
                <select class="form-select form-select-sm" name="dept">
                    <option value="">全部部门</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected($deptId === $d->id)>{{ $d->code }} {{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">状态</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">全部</option>
                    @foreach ($statusText as $k => $v)
                        <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">员工</label>
                <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="工号 / 姓名">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">查询</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.attendance.records') }}">重置</a>
            </div>
        </form>
    </div>

    <div class="hr-card mb-3">
        <h3>逐日正常率</h3>
        @if ($trend === [])
            <div class="hr-empty">这个区间还没有打卡数据。</div>
        @else
            <div class="d-flex align-items-end gap-2" style="height:96px">
                @foreach ($trend as $t)
                    <div class="text-center" style="flex:1">
                        <div style="height:70px; display:flex; align-items:flex-end">
                            <div style="width:100%; height:{{ max(6, $t['rate']) }}%; background:linear-gradient(180deg,#9b7ff0,#5f3dc4); border-radius:4px 4px 0 0"
                                 title="{{ $t['date'] }} · {{ $t['normal'] }}/{{ $t['total'] }} 正常"></div>
                        </div>
                        <div style="font-size:11px; color:#9a9aad; margin-top:4px">{{ substr($t['date'], 5) }}</div>
                        <div style="font-size:11px; color:#5f3dc4">{{ $t['rate'] }}%</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="hr-card">
        <h3>明细 {{ $rows->total() }} 条</h3>
        <table class="hr-table">
            <thead>
            <tr><th>日期</th><th>员工</th><th>部门</th><th>班次</th><th>上班</th><th>下班</th><th>状态</th><th class="num">迟到/早退</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r->work_date?->format('m-d') ?? '—' }}</td>
                    <td>
                        @if ($r->employee)
                            <a href="{{ route('hr.employees.show', $r->employee->id) }}" class="text-decoration-none">{{ $r->employee->name }}</a>
                            <span style="color:#9a9aad; font-size:12px">{{ $r->employee->employee_no }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $r->employee?->department?->name ?? '—' }}</td>
                    <td>{{ $r->shift?->code ?? '—' }}</td>
                    <td>{{ $r->clock_in ?? '—' }}</td>
                    <td>{{ $r->clock_out ?? '—' }}</td>
                    <td>
                        @php($map = ['normal' => 'b-done', 'late' => 'b-bad', 'early' => 'b-progress', 'absent' => 'b-bad', 'missing' => 'b-draft'])
                        <span class="hr-badge {{ $map[$r->status] ?? 'b-draft' }}">{{ $statusText[$r->status] ?? $r->status }}</span>
                    </td>
                    <td class="num" style="font-size:12px; color:#6f6f80">
                        @if ($r->late_minutes) 迟 {{ $r->late_minutes }} 分 @endif
                        @if ($r->early_minutes) 早 {{ $r->early_minutes }} 分 @endif
                        @if (! $r->late_minutes && ! $r->early_minutes) — @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="hr-empty">这个条件下没有打卡记录。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>
@endsection
