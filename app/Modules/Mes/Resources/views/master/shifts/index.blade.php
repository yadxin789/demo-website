@extends('mes::layouts.app')

@section('title', '班次')
@section('sub', '白班 / 夜班 · 跨天标记')

@section('content')
    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px">
            <div style="color:#6b7a90; font-size:13px">报工时挂班次，用于统计白/夜班产出与良率。</div>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#shiftModal" onclick="fillShift(null)">+ 新增班次</button>
        </div>

        <table class="mes-table">
            <thead>
            <tr>
                <th>编码</th>
                <th>名称</th>
                <th>开始</th>
                <th>结束</th>
                <th>跨天</th>
                <th>时长</th>
                <th>状态</th>
                <th style="width:70px"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($shifts as $s)
                @php
                    $startAt = \Carbon\Carbon::createFromFormat('H:i', $s->start_time);
                    $endAt = \Carbon\Carbon::createFromFormat('H:i', $s->end_time);
                    if ($s->cross_day || $endAt->lt($startAt)) { $endAt->addDay(); }
                    $duration = $startAt->diffInMinutes($endAt) / 60;
                @endphp
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">{{ $s->code }}</td>
                    <td>{{ $s->name }}</td>
                    <td>{{ $s->start_time }}</td>
                    <td>{{ $s->end_time }}</td>
                    <td>{{ $s->cross_day ? '是' : '否' }}</td>
                    <td>{{ round($duration, 1) }} h</td>
                    <td><span class="mes-badge {{ $s->is_active ? 'b-done' : 'b-draft' }}">{{ $s->is_active ? '启用' : '停用' }}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                data-bs-toggle="modal" data-bs-target="#shiftModal"
                                onclick='fillShift(@json($s))'>编辑</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="shiftModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" id="shiftForm" action="{{ route('mes.master.shifts.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST" id="shiftMethod">
                <div class="modal-header">
                    <h5 class="modal-title" id="shiftTitle">新增班次</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
                    @endif
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">编码</label>
                            <input name="code" class="form-control" value="{{ old('code') }}" placeholder="SH-A" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">名称</label>
                            <input name="name" class="form-control" value="{{ old('name') }}" placeholder="白班" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">开始时间</label>
                            <input name="start_time" type="time" class="form-control" value="{{ old('start_time', '08:00') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">结束时间</label>
                            <input name="end_time" type="time" class="form-control" value="{{ old('end_time', '20:00') }}" required>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="cross_day" value="1" id="crossDay">
                                <label class="form-check-label" for="crossDay">跨天（夜班）</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="shiftActive" checked>
                                <label class="form-check-label" for="shiftActive">启用</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                    <button class="btn btn-primary" type="submit">保存</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function fillShift(s) {
    const form = document.getElementById('shiftForm');
    const method = document.getElementById('shiftMethod');
    const title = document.getElementById('shiftTitle');

    if (!s) {
        form.action = "{{ route('mes.master.shifts.store') }}";
        method.value = 'POST';
        title.textContent = '新增班次';
        form.reset();
        return;
    }
    form.action = "/mes/master/shifts/" + s.id;
    method.value = 'PUT';
    title.textContent = '编辑班次 · ' + s.code;
    form.querySelector('[name=code]').value = s.code;
    form.querySelector('[name=name]').value = s.name;
    form.querySelector('[name=start_time]').value = s.start_time;
    form.querySelector('[name=end_time]').value = s.end_time;
    form.querySelector('[name=cross_day]').checked = !!s.cross_day;
    form.querySelector('[name=is_active]').checked = !!s.is_active;
}
</script>
@endpush
