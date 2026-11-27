@extends('hr::layouts.app')

@section('title', '假期余额')
@section('sub', '华瑞集团（控股）· 每人每年假 / 病假 / 事假 / 调休 的总额与剩余')

@section('content')
    <div class="hr-kpis mb-3">
        @foreach (['annual', 'compensatory', 'sick', 'personal'] as $t)
            @php($row = $sum[$t] ?? null)
            <div class="hr-kpi {{ $t === 'annual' ? 'purple' : ($t === 'sick' ? 'bad' : 'ok') }}">
                <div class="lbl">{{ $typeText[$t] }}剩余</div>
                <div class="num">{{ $row ? rtrim(rtrim(number_format((float) $row->r, 1), '0'), '.') : 0 }}</div>
                <div class="hint">总额 {{ $row ? rtrim(rtrim(number_format((float) $row->t, 1), '0'), '.') : 0 }} · 已用 {{ $row ? rtrim(rtrim(number_format((float) $row->u, 1), '0'), '.') : 0 }}</div>
            </div>
        @endforeach
    </div>

    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-2">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">年度</label>
                <input class="form-control form-control-sm" name="year" type="number" value="{{ $year }}">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">类型</label>
                <select class="form-select form-select-sm" name="type">
                    <option value="">全部类型</option>
                    @foreach ($typeText as $k => $v)
                        <option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>
                    @endforeach
                </select>
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
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">员工</label>
                <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="工号 / 姓名">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">查询</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.leaves.balances') }}">重置</a>
            </div>
        </form>
    </div>

    <div class="hr-card">
        <h3>余额明细 {{ $rows->total() }} 行</h3>
        <table class="hr-table">
            <thead>
            <tr><th>员工</th><th>部门</th><th>类型</th><th class="num">总额</th><th class="num">已用</th><th class="num">剩余</th><th>用量</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $b)
                @php($total = (float) $b->total)
                @php($used = (float) $b->used)
                @php($pct = $total > 0 ? min(100, (int) round($used / $total * 100)) : 0)
                <tr>
                    <td>
                        @if ($b->employee)
                            <a href="{{ route('hr.employees.show', $b->employee->id) }}" class="text-decoration-none">{{ $b->employee->name }}</a>
                            <span style="color:#9a9aad; font-size:12px">{{ $b->employee->employee_no }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $b->employee?->department?->name ?? '—' }}</td>
                    <td>{{ $typeText[$b->type] ?? $b->type }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($total, 1), '0'), '.') }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($used, 1), '0'), '.') }}</td>
                    <td class="num" style="font-weight:600">{{ rtrim(rtrim(number_format((float) $b->remaining, 1), '0'), '.') }}</td>
                    <td style="min-width:110px">
                        <div class="hr-progress"><span style="width: {{ $pct }}%; background: {{ $pct >= 80 ? '#d6336c' : '#5f3dc4' }}"></span></div>
                        <span style="font-size:11px; color:#9a9aad">{{ $pct }}%</span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="hr-empty">这个条件下没有余额记录。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>
@endsection
