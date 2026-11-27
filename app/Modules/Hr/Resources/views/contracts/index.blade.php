@extends('hr::layouts.app')

@section('title', '合同与到期')
@section('sub', '30 / 60 / 90 天到期预警')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi purple">
            <div class="lbl">有效合同</div>
            <div class="num">{{ $kpi['active'] }}</div>
            <div class="hint">在职员工名下</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">30 天内到期</div>
            <div class="num">{{ $kpi['d30'] }}</div>
            <div class="hint">最紧急</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">60 天内到期</div>
            <div class="num">{{ $kpi['d60'] }}</div>
            <div class="hint">含上面 30 天</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">90 天内到期</div>
            <div class="num">{{ $kpi['d90'] }}</div>
            <div class="hint">含上面 60 天</div>
        </div>
    </div>

    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">关键字</label>
                <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="工号 / 姓名">
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
            <div class="col-md-3">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">到期窗口</label>
                <select class="form-select form-select-sm" name="window">
                    <option value="0" @selected($window === 0)>全部有效合同</option>
                    <option value="30" @selected($window === 30)>30 天内</option>
                    <option value="60" @selected($window === 60)>60 天内</option>
                    <option value="90" @selected($window === 90)>90 天内</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">筛选</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.employees.contracts') }}">重置</a>
                <button class="btn btn-sm btn-outline-primary ms-auto" type="button" data-bs-toggle="modal"
                        data-bs-target="#ctModal">新签合同</button>
            </div>
        </form>
    </div>

    <div class="hr-card">
        <h3>合同清单（{{ $rows->total() }} 份，按到期日升序）</h3>
        <table class="hr-table">
            <thead>
            <tr><th>合同号</th><th>员工</th><th>部门</th><th>类型</th><th>起始</th><th>到期</th><th>剩余</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $c)
                @php($due = $c->end_date ? $today->diffInDays($c->end_date, false) : null)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">{{ $c->contract_no }}</td>
                    <td>
                        @if ($c->employee)
                            <a href="{{ route('hr.employees.show', $c->employee->id) }}" class="text-decoration-none">{{ $c->employee->name }}</a>
                            <span style="color:#9a9aad; font-size:12px">{{ $c->employee->employee_no }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>{{ $c->employee?->department?->name ?? '—' }}</td>
                    <td>{{ $c->type === 'open' ? '无固定期限' : '固定期限' }}</td>
                    <td>{{ $c->start_date?->format('Y-m-d') }}</td>
                    <td>{{ $c->end_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        @if ($due === null)
                            <span class="hr-badge b-done">长期</span>
                        @elseif ($due < 0)
                            <span class="hr-badge b-bad">已过期 {{ abs($due) }} 天</span>
                        @elseif ($due <= 30)
                            <span class="hr-badge b-bad">{{ $due }} 天</span>
                        @elseif ($due <= 60)
                            <span class="hr-badge b-progress">{{ $due }} 天</span>
                        @elseif ($due <= 90)
                            <span class="hr-badge b-draft">{{ $due }} 天</span>
                        @else
                            <span class="hr-badge b-done">{{ $due }} 天</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="hr-empty">这个窗口内没有到期合同。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>

    <div class="modal fade" id="ctModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="{{ route('hr.employees.contracts.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">新签 / 续签合同</h5>
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
                                <label class="form-label">合同类型</label>
                                <select class="form-select" name="type" required>
                                    <option value="fixed">固定期限</option>
                                    <option value="open">无固定期限</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">起始日</label>
                                <input class="form-control" name="start_date" type="date" value="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">到期日</label>
                                <input class="form-control" name="end_date" type="date">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">取消</button>
                        <button class="btn btn-primary" type="submit">登记合同</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
