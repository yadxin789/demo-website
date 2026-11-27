@extends('hr::layouts.app')

@section('title', '异动记录')
@section('sub', '华瑞集团（控股）· 入职 / 转正 / 调岗 / 离职流水')

@section('content')
    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">关键字</label>
                <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="工号 / 姓名">
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">类型</label>
                <select class="form-select form-select-sm" name="type">
                    <option value="">全部类型</option>
                    @foreach ($movementText as $k => $v)
                        <option value="{{ $k }}" @selected($type === $k)>{{ $v }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">筛选</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.employees.movements') }}">重置</a>
                <button class="btn btn-sm btn-outline-primary ms-auto" type="button" data-bs-toggle="modal"
                        data-bs-target="#mvModal">登记异动</button>
            </div>
        </form>
    </div>

    <div class="hr-card">
        <h3>异动流水 {{ $rows->total() }} 条</h3>
        <table class="hr-table">
            <thead>
            <tr><th>生效日</th><th>员工</th><th>类型</th><th>从</th><th>到</th><th>说明</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $m)
                <tr>
                    <td>{{ $m->effective_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        @if ($m->employee)
                            <a href="{{ route('hr.employees.show', $m->employee->id) }}" class="text-decoration-none">{{ $m->employee->name }}</a>
                            <span style="color:#9a9aad; font-size:12px">{{ $m->employee->employee_no }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @php($map = ['hire' => 'b-done', 'regular' => 'b-progress', 'transfer' => 'b-draft', 'promote' => 'b-released', 'leave' => 'b-bad'])
                        <span class="hr-badge {{ $map[$m->type] ?? 'b-draft' }}">{{ $movementText[$m->type] ?? $m->type }}</span>
                    </td>
                    <td>
                        {{ $m->fromDepartment?->name ?? '—' }}
                        @if ($m->fromPosition) <span style="color:#9a9aad; font-size:12px">/ {{ $m->fromPosition->name }}</span> @endif
                    </td>
                    <td>
                        {{ $m->toDepartment?->name ?? '—' }}
                        @if ($m->toPosition) <span style="color:#9a9aad; font-size:12px">/ {{ $m->toPosition->name }}</span> @endif
                    </td>
                    <td style="color:#6f6f80">{{ $m->remark ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="hr-empty">没有匹配的异动记录。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>

    <div class="modal fade" id="mvModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="{{ route('hr.employees.movements.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">登记异动</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">员工</label>
                                <select class="form-select" name="employee_id" id="mvEmp" required>
                                    <option value="">选择员工</option>
                                    @foreach ($employees as $e)
                                        <option value="{{ $e->id }}">{{ $e->employee_no }} {{ $e->name }}（{{ $e->status === 'probation' ? '试用' : ($e->status === 'left' ? '离职' : '在职') }}）</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">类型</label>
                                <select class="form-select" name="type" id="mvType" required>
                                    <option value="transfer">调岗</option>
                                    <option value="regular">转正</option>
                                    <option value="leave">离职</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">生效日</label>
                                <input class="form-control" name="effective_date" type="date" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-6" id="mvDeptWrap">
                                <label class="form-label">新部门</label>
                                <select class="form-select" name="department_id">
                                    <option value="">不变</option>
                                    @foreach ($departments as $d)
                                        <option value="{{ $d->id }}">{{ $d->code }} {{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6" id="mvPosWrap">
                                <label class="form-label">新岗位</label>
                                <select class="form-select" name="position_id">
                                    <option value="">不变</option>
                                    @foreach ($positions as $p)
                                        <option value="{{ $p->id }}">{{ $p->code }} {{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">说明</label>
                                <input class="form-control" name="reason" placeholder="调岗原因 / 离职原因">
                            </div>
                        </div>
                        <div class="form-text mt-2">调岗会写 transfer 异动并同步编制；转正要求员工在试用状态；离职会回写编制在职数。</div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">取消</button>
                        <button class="btn btn-primary" type="submit">提交</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    const mvType = document.getElementById('mvType');
    const deptWrap = document.getElementById('mvDeptWrap');
    const posWrap = document.getElementById('mvPosWrap');
    mvType.addEventListener('change', function () {
        const isTransfer = this.value === 'transfer';
        deptWrap.style.display = isTransfer ? '' : 'none';
        posWrap.style.display = isTransfer ? '' : 'none';
    });
</script>
@endpush
