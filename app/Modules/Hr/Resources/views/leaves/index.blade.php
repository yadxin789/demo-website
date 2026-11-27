@extends('hr::layouts.app')

@section('title', '请假审批')
@section('sub', '华瑞集团（控股）· 批准即扣年假/调休余额，驳回要写原因')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi bad">
            <div class="lbl">待审批</div>
            <div class="num">{{ $kpi['pending'] }}</div>
            <div class="hint">HR / 主管确认</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">已批准</div>
            <div class="num">{{ $kpi['approved'] }}</div>
            <div class="hint">累计 {{ $kpi['days'] }} 天</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">已驳回</div>
            <div class="num">{{ $kpi['rejected'] }}</div>
            <div class="hint">需写原因</div>
        </div>
    </div>

    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-2">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">状态</label>
                <select class="form-select form-select-sm" name="status">
                    <option value="">全部</option>
                    @foreach ($statusText as $k => $v)
                        <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
                    @endforeach
                </select>
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
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">员工</label>
                <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="工号 / 姓名">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">筛选</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.leaves.index') }}">重置</a>
                <a class="btn btn-sm btn-outline-primary ms-auto" href="{{ route('hr.leaves.create') }}">提交请假</a>
            </div>
        </form>
    </div>

    <div class="hr-card">
        <h3>请假单 {{ $rows->total() }} 条</h3>
        <table class="hr-table">
            <thead>
            <tr><th>单号</th><th>员工</th><th>部门</th><th>类型</th><th>起止</th><th class="num">天数</th><th>事由</th><th>状态</th><th>审批</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $l)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $l->leave_no }}</td>
                    <td>
                        @if ($l->employee)
                            <a href="{{ route('hr.employees.show', $l->employee->id) }}" class="text-decoration-none">{{ $l->employee->name }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $l->employee?->department?->name ?? '—' }}</td>
                    <td>{{ $typeText[$l->type] ?? $l->type }}</td>
                    <td style="font-size:12px">{{ $l->start_date?->format('m-d') }} ~ {{ $l->end_date?->format('m-d') }}</td>
                    <td class="num" style="font-weight:600">{{ rtrim(rtrim(number_format((float) $l->days, 1), '0'), '.') }}</td>
                    <td style="color:#6f6f80">{{ $l->reason ?? '—' }}</td>
                    <td>
                        @php($map = ['pending' => 'b-progress', 'approved' => 'b-done', 'rejected' => 'b-bad'])
                        <span class="hr-badge {{ $map[$l->status] ?? 'b-draft' }}">{{ $statusText[$l->status] ?? $l->status }}</span>
                        @if ($l->status === 'rejected' && $l->reject_reason)
                            <div style="font-size:11px; color:#9a9aad">原因：{{ $l->reject_reason }}</div>
                        @endif
                    </td>
                    <td>
                        @if ($l->status === 'pending')
                            <div class="d-flex gap-1">
                                <form method="post" action="{{ route('hr.leaves.approve', $l->id) }}" style="margin:0">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary" type="submit">批准</button>
                                </form>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal"
                                        data-bs-target="#rejectModal" onclick="openReject({{ $l->id }}, '{{ $l->leave_no }}')">驳回</button>
                            </div>
                        @else
                            <span style="font-size:12px; color:#9a9aad">{{ $l->approver?->name ?? '—' }}
                                @if ($l->approved_at) · {{ $l->approved_at->format('m-d H:i') }} @endif
                            </span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="hr-empty">这个条件下没有请假单。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="rejectForm" action="">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">驳回请假 · <span id="rejectNo"></span></h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">驳回原因（必填）</label>
                        <input class="form-control" name="reject_reason" placeholder="如同一时段请假人数已满" required>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">取消</button>
                        <button class="btn btn-danger" type="submit">确认驳回</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function openReject(id, no) {
        document.getElementById('rejectForm').action = "{{ url('hr/leaves') }}/" + id + "/reject";
        document.getElementById('rejectNo').textContent = no;
    }
</script>
@endpush
