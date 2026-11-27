@extends('hr::layouts.app')

@section('title', '员工档案')
@section('sub', '华瑞集团（控股）· 80 人的入转调离都在这张表里')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi ok">
            <div class="lbl">在职</div>
            <div class="num">{{ $kpi['active'] }}</div>
            <div class="hint">已转正</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">试用</div>
            <div class="num">{{ $kpi['probation'] }}</div>
            <div class="hint">待办转正</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">离职</div>
            <div class="num">{{ $kpi['left'] }}</div>
            <div class="hint">历史沉淀</div>
        </div>
        <div class="hr-kpi purple">
            <div class="lbl">本月入职</div>
            <div class="num">{{ $kpi['month_hire'] }}</div>
            <div class="hint">{{ now()->format('Y 年 m 月') }}</div>
        </div>
    </div>

    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">关键字</label>
                <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="工号 / 姓名 / 手机 / 邮箱">
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
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">筛选</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.employees.index') }}">重置</a>
                <a class="btn btn-sm btn-outline-primary ms-auto" href="{{ route('hr.employees.create') }}">新增员工</a>
            </div>
        </form>
    </div>

    <div class="hr-card">
        <h3>员工 {{ $rows->total() }} 人</h3>
        <table class="hr-table">
            <thead>
            <tr>
                <th>工号</th><th>姓名</th><th>性别</th><th>部门</th><th>岗位</th>
                <th>入职日</th><th>状态</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $e)
                <tr>
                    <td><span style="font-family:ui-monospace,Consolas,monospace">{{ $e->employee_no }}</span></td>
                    <td><a href="{{ route('hr.employees.show', $e->id) }}" class="text-decoration-none">{{ $e->name }}</a></td>
                    <td>{{ $e->gender === 'male' ? '男' : '女' }}</td>
                    <td>{{ $e->department?->name ?? '—' }}</td>
                    <td>{{ $e->position?->name ?? '—' }}</td>
                    <td>{{ $e->hire_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        <span class="hr-badge {{ $e->status === 'active' ? 'b-done' : ($e->status === 'probation' ? 'b-progress' : 'b-draft') }}">
                            {{ $statusText[$e->status] ?? $e->status }}
                        </span>
                    </td>
                    <td><a class="btn btn-sm btn-link p-0" href="{{ route('hr.employees.show', $e->id) }}">查看</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="hr-empty">没有匹配的员工。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>
@endsection
