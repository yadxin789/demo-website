@extends('hr::layouts.app')

@section('title', '考勤异常')
@section('sub', '华瑞集团（控股）· 待处理的先来，处理完待办立刻减少')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi bad">
            <div class="lbl">待处理</div>
            <div class="num">{{ $kpi['open'] }}</div>
            <div class="hint">需要 HR 定处理方式</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">已处理</div>
            <div class="num">{{ $kpi['handled'] }}</div>
            <div class="hint">补卡 / 不计 / 扣款</div>
        </div>
        <div class="hr-kpi purple">
            <div class="lbl">异常合计</div>
            <div class="num">{{ $kpi['total'] }}</div>
            <div class="hint">近一个月</div>
        </div>
    </div>

    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">处理状态</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">全部</option>
                    <option value="open" @selected($status === 'open')>待处理</option>
                    <option value="handled" @selected($status === 'handled')>已处理</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">异常类型</label>
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
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">筛选</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.attendance.exceptions') }}">重置</a>
            </div>
        </form>
    </div>

    <div class="hr-card">
        <h3>异常 {{ $rows->total() }} 条</h3>
        <table class="hr-table">
            <thead>
            <tr><th>日期</th><th>员工</th><th>部门</th><th>类型</th><th>说明</th><th>处理</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $e)
                <tr>
                    <td>{{ $e->work_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        @if ($e->employee)
                            <a href="{{ route('hr.employees.show', $e->employee->id) }}" class="text-decoration-none">{{ $e->employee->name }}</a>
                            <span style="color:#9a9aad; font-size:12px">{{ $e->employee->employee_no }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $e->employee?->department?->name ?? '—' }}</td>
                    <td>
                        @php($map = ['late' => 'b-bad', 'early' => 'b-progress', 'missing' => 'b-draft'])
                        <span class="hr-badge {{ $map[$e->type] ?? 'b-draft' }}">{{ $typeText[$e->type] ?? $e->type }}</span>
                    </td>
                    <td style="color:#6f6f80">{{ $e->reason ?? '—' }}</td>
                    <td>
                        @if ($e->status === 'open')
                            <form method="post" action="{{ route('hr.attendance.exceptions.handle', $e->id) }}" class="d-flex gap-1" style="margin:0">
                                @csrf
                                <select class="form-select form-select-sm" name="result" style="width:88px">
                                    <option value="补卡">补卡</option>
                                    <option value="不计">不计</option>
                                    <option value="扣款">扣款</option>
                                    <option value="调休">调休</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary" type="submit">处理</button>
                            </form>
                        @else
                            <span class="hr-badge b-done">{{ $e->result ?? '已处理' }}</span>
                            <span style="font-size:11px; color:#9a9aad">{{ $e->handler?->name ?? '' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="hr-empty">这个条件下没有异常记录。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>
@endsection
