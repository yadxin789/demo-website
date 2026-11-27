@extends('hr::layouts.app')

@section('title', '班次')
@section('sub', '标准班 / 早班 / 夜班，迟到容忍按班次单独设')

@section('content')
    <div class="hr-card mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h3 style="margin:0">班次设置（{{ $rows->count() }} 个）</h3>
                <div style="color:#6f6f80; font-size:12px; margin-top:4px">改班次只影响后续判定，已生成的打卡记录不会重算。</div>
            </div>
            <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#shiftModal"
                    onclick="openShift(null)">新增班次</button>
        </div>
    </div>

    <div class="hr-card">
        <table class="hr-table">
            <thead>
            <tr>
                <th>编码</th><th>名称</th><th>上班</th><th>下班</th><th>跨天</th>
                <th class="num">迟到容忍</th><th class="num">打卡记录</th><th>状态</th><th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $s)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">{{ $s->code }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->work_start }}</td>
                    <td>{{ $s->work_end }}</td>
                    <td>{{ $s->cross_day ? '次日下班' : '当天' }}</td>
                    <td class="num">{{ $s->late_tolerance_min }} 分钟</td>
                    <td class="num">{{ (int) ($recordCounts[$s->id] ?? 0) }}</td>
                    <td>
                        <span class="hr-badge {{ $s->status === 'active' ? 'b-done' : 'b-draft' }}">
                            {{ $s->status === 'active' ? '启用' : '停用' }}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="modal" data-bs-target="#shiftModal"
                                onclick='openShift({{ json_encode(["id" => $s->id, "code" => $s->code, "name" => $s->name, "work_start" => $s->work_start, "work_end" => $s->work_end, "late_tolerance_min" => $s->late_tolerance_min, "cross_day" => (bool) $s->cross_day, "status" => $s->status], JSON_UNESCAPED_UNICODE) }})'>编辑</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="shiftModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="shiftForm" action="{{ route('hr.attendance.shifts.store') }}">
                    @csrf
                    <input type="hidden" name="_method" id="shiftMethod" value="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="shiftTitle">新增班次</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-4">
                                <label class="form-label">编码</label>
                                <input class="form-control" name="code" id="shCode" placeholder="S04" required>
                            </div>
                            <div class="col-8">
                                <label class="form-label">名称</label>
                                <input class="form-control" name="name" id="shName" placeholder="中班" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">上班</label>
                                <input class="form-control" name="work_start" id="shStart" type="time" value="09:00" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">下班</label>
                                <input class="form-control" name="work_end" id="shEnd" type="time" value="18:00" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label">迟到容忍（分钟）</label>
                                <input class="form-control" name="late_tolerance_min" id="shTol" type="number" min="0" max="60" value="5">
                            </div>
                            <div class="col-6">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" id="shCross" name="cross_day" type="checkbox" value="1">
                                    <label class="form-check-label" for="shCross">跨天（次日下班）</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">状态</label>
                                <select class="form-select" name="status" id="shStatus">
                                    <option value="active">启用</option>
                                    <option value="inactive">停用</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">取消</button>
                        <button class="btn btn-primary" type="submit">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function openShift(s) {
        const form = document.getElementById('shiftForm');
        const title = document.getElementById('shiftTitle');
        const method = document.getElementById('shiftMethod');

        if (!s) {
            title.textContent = '新增班次';
            form.action = "{{ route('hr.attendance.shifts.store') }}";
            method.value = 'post';
            document.getElementById('shCode').value = '';
            document.getElementById('shName').value = '';
            document.getElementById('shStart').value = '09:00';
            document.getElementById('shEnd').value = '18:00';
            document.getElementById('shTol').value = 5;
            document.getElementById('shCross').checked = false;
            document.getElementById('shStatus').value = 'active';
            return;
        }

        title.textContent = '编辑班次 · ' + s.code;
        form.action = "{{ url('hr/attendance/shifts') }}/" + s.id;
        method.value = 'put';
        document.getElementById('shCode').value = s.code;
        document.getElementById('shName').value = s.name;
        document.getElementById('shStart').value = s.work_start.substring(0, 5);
        document.getElementById('shEnd').value = s.work_end.substring(0, 5);
        document.getElementById('shTol').value = s.late_tolerance_min;
        document.getElementById('shCross').checked = s.cross_day;
        document.getElementById('shStatus').value = s.status;
    }
</script>
@endpush
