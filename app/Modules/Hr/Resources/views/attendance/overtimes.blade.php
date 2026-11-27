@extends('hr::layouts.app')

@section('title', '加班')
@section('sub', '华瑞集团（控股）· 申请 → 审批 → 计入工时')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi bad">
            <div class="lbl">待审批</div>
            <div class="num">{{ $kpi['pending'] }}</div>
            <div class="hint">等主管确认</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">已批准</div>
            <div class="num">{{ $kpi['approved'] }}</div>
            <div class="hint">累计 {{ $kpi['hours'] }} 小时</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">已驳回</div>
            <div class="num">{{ $kpi['rejected'] }}</div>
            <div class="hint">不计工时</div>
        </div>
    </div>

    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">审批状态</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">全部</option>
                    <option value="pending" @selected($status === 'pending')>待审批</option>
                    <option value="approved" @selected($status === 'approved')>已批准</option>
                    <option value="rejected" @selected($status === 'rejected')>已驳回</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">加班类型</label>
                <select class="form-select form-select-sm" name="type">
                    <option value="">全部类型</option>
                    @foreach ($typeText as $k => $v)
                        <option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">筛选</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.attendance.overtimes') }}">重置</a>
                <button class="btn btn-sm btn-outline-primary ms-auto" type="button" data-bs-toggle="modal"
                        data-bs-target="#otModal">登记加班</button>
            </div>
        </form>
    </div>

    <div class="hr-card">
        <h3>加班单 {{ $rows->total() }} 条</h3>
        <table class="hr-table">
            <thead>
            <tr><th>日期</th><th>员工</th><th>部门</th><th>类型</th><th class="num">小时</th><th>事由</th><th>状态</th><th>审批</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $o)
                <tr>
                    <td>{{ $o->date?->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        @if ($o->employee)
                            <a href="{{ route('hr.employees.show', $o->employee->id) }}" class="text-decoration-none">{{ $o->employee->name }}</a>
                            <span style="color:#9a9aad; font-size:12px">{{ $o->employee->employee_no }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $o->employee?->department?->name ?? '—' }}</td>
                    <td>{{ $typeText[$o->type] ?? $o->type }}</td>
                    <td class="num" style="font-weight:600">{{ (float) $o->hours }}</td>
                    <td style="color:#6f6f80">{{ $o->reason ?? '—' }}</td>
                    <td>
                        @php($map = ['pending' => 'b-progress', 'approved' => 'b-done', 'rejected' => 'b-bad'])
                        <span class="hr-badge {{ $map[$o->status] ?? 'b-draft' }}">
                            {{ ['pending' => '待审批', 'approved' => '已批准', 'rejected' => '已驳回'][$o->status] ?? $o->status }}
                        </span>
                    </td>
                    <td>
                        @if ($o->status === 'pending')
                            <div class="d-flex gap-1">
                                <form method="post" action="{{ route('hr.attendance.overtimes.approve', $o->id) }}" style="margin:0">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary" type="submit">批准</button>
                                </form>
                                <form method="post" action="{{ route('hr.attendance.overtimes.reject', $o->id) }}" style="margin:0">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-danger" type="submit">驳回</button>
                                </form>
                            </div>
                        @else
                            <span style="font-size:12px; color:#9a9aad">{{ $o->approver?->name ?? '—' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="hr-empty">这个条件下没有加班记录。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>

    <div class="modal fade" id="otModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="{{ route('hr.attendance.overtimes.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">登记加班</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">员工</label>
                                <select class="form-select" name="employee_id" required>
                                    <option value="">选择员工</option>
                                    @foreach ($employees as $e)
                                        <option value="{{ $e->id }}">{{ $e->employee_no }} {{ $e->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">加班日期</label>
                                <input class="form-control" name="date" type="date" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">小时数</label>
                                <input class="form-control" name="hours" type="number" step="0.5" min="0.5" max="24" value="3" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">类型</label>
                                <select class="form-select" name="type" required>
                                    @foreach ($typeText as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">事由</label>
                                <input class="form-control" name="reason" placeholder="月末结账 / 客户上线支持">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">取消</button>
                        <button class="btn btn-primary" type="submit">提交申请</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
