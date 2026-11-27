@extends('hr::layouts.app')

@section('title', '排班表')
@section('sub', '按周排班：谁上哪个班，一眼看清覆盖与空缺')

@section('content')
    <div class="hr-card mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.attendance.roster', ['week' => $weekStart->copy()->subWeek()->toDateString(), 'dept' => $dept]) }}">上一周</a>
                <div style="font-weight:700">{{ $weekStart->toDateString() }} ~ {{ $weekStart->copy()->endOfWeek()->toDateString() }}</div>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.attendance.roster', ['week' => $weekStart->copy()->addWeek()->toDateString(), 'dept' => $dept]) }}">下一周</a>
                <a class="btn btn-sm btn-link" href="{{ route('hr.attendance.roster', ['dept' => $dept]) }}">回到本周</a>
            </div>
            <form method="get" class="d-flex gap-2">
                <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
                <select class="form-select form-select-sm" name="dept" style="width:160px">
                    <option value="">全部部门</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected((string) $d->id === $dept)>{{ $d->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-primary">筛选</button>
            </form>
        </div>
        <div class="mt-2" style="font-size:12px;color:#8a97a8">
            本周排班 {{ $stat['planned'] }} 人次 · 覆盖员工 {{ $stat['people'] }} 人 · 请假 {{ $stat['leave'] }} 人次 · 覆盖率 {{ $stat['coverage'] }}%
        </div>
    </div>

    <div class="hr-card mb-3">
        <div style="overflow-x:auto">
            <table class="hr-table" style="min-width:900px">
                <thead>
                <tr>
                    <th style="min-width:150px">员工</th>
                    @foreach ($days as $d)
                        <th class="num" style="{{ $d->isWeekend() ? 'background:#f8f9fa' : '' }}">
                            {{ ['一','二','三','四','五','六','日'][$d->dayOfWeekIso - 1] }}
                            <div style="font-weight:400;font-size:11px;color:#8a97a8">{{ $d->format('m-d') }}</div>
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse ($employees as $e)
                    <tr>
                        <td>{{ $e->employee_no }} {{ $e->name }}<div style="font-size:11px;color:#8a97a8">{{ $e->department?->name }} · {{ $e->position?->name }}</div></td>
                        @foreach ($days as $d)
                            @php($a = $grid[$e->id][$d->toDateString()] ?? null)
                            <td class="num" style="{{ $d->isWeekend() ? 'background:#fbfcfd' : '' }}">
                                @if ($a)
                                    <form method="post" action="{{ route('hr.attendance.roster.mark', $a) }}">
                                        @csrf
                                        <button class="btn btn-sm p-1 {{ $a->status === 'leave' ? 'btn-outline-warning' : ($a->status === 'absent' ? 'btn-outline-danger' : ($a->status === 'actual' ? 'btn-outline-success' : 'btn-outline-secondary')) }}"
                                                title="{{ $a->shift?->name }} {{ $a->shift?->work_start }}-{{ $a->shift?->work_end }}（点一下切换状态）"
                                                onclick="this.form.status.value='{{ $a->status === 'planned' ? 'actual' : ($a->status === 'actual' ? 'leave' : 'planned') }}'">
                                            {{ $a->shift?->code }}
                                        </button>
                                        <input type="hidden" name="status" value="{{ $a->status }}">
                                    </form>
                                @else
                                    <span style="color:#dee2e6">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="8" style="color:#8a97a8">没有在职员工。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="font-size:12px;color:#8a97a8;margin-top:8px">点格子里的班次代码可切换状态：灰=已排、绿=实际出勤、黄=请假、红=缺勤。</div>
    </div>

    <div class="hr-card">
        <h3 style="margin:0 0 10px">批量排班</h3>
        <form method="post" action="{{ route('hr.attendance.roster.store') }}">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-5">
                    <label class="form-label">员工（可多选，Ctrl 点选）</label>
                    <select class="form-select form-select-sm" name="employee_ids[]" multiple size="6" required>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}">{{ $e->employee_no }} {{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-3">
                    <label class="form-label">班次</label>
                    <select class="form-select form-select-sm" name="shift_id" required>
                        @foreach ($shifts as $s)
                            <option value="{{ $s->id }}">{{ $s->code }} {{ $s->name }}（{{ $s->work_start }}-{{ $s->work_end }}）</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-2">
                    <label class="form-label">从</label>
                    <input class="form-control form-control-sm" type="date" name="from" value="{{ $weekStart->toDateString() }}" required>
                </div>
                <div class="col-2">
                    <label class="form-label">到</label>
                    <input class="form-control form-control-sm" type="date" name="to" value="{{ $weekStart->copy()->endOfWeek()->toDateString() }}" required>
                </div>
                <div class="col-12 mt-2"><button class="btn btn-primary btn-sm">按区间排班</button></div>
            </div>
        </form>
    </div>
@endsection
