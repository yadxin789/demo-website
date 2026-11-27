@extends('hr::layouts.app')

@section('title', '培训管理')
@section('sub', '课程库 → 开班场次 → 报名与结果，培训记录进员工档案')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-3">
            <div class="hr-card">
                <div style="color:#6f6f80; font-size:12px">课程</div>
                <div style="font-size:24px; font-weight:700">{{ $stat['courses'] }}<small style="font-size:12px"> 门</small></div>
            </div>
        </div>
        <div class="col-3">
            <div class="hr-card">
                <div style="color:#6f6f80; font-size:12px">开班场次</div>
                <div style="font-size:24px; font-weight:700">{{ $stat['sessions'] }}<small style="font-size:12px"> 期</small></div>
            </div>
        </div>
        <div class="col-3">
            <div class="hr-card">
                <div style="color:#6f6f80; font-size:12px">累计报名</div>
                <div style="font-size:24px; font-weight:700">{{ $stat['enrolled'] }}<small style="font-size:12px"> 人次</small></div>
            </div>
        </div>
        <div class="col-3">
            <div class="hr-card">
                <div style="color:#6f6f80; font-size:12px">通过率</div>
                <div style="font-size:24px; font-weight:700">{{ number_format($stat['pass_rate'], 1) }}<small style="font-size:12px">%</small></div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-7">
            <div class="hr-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 style="margin:0">课程库</h3>
                    <div style="font-size:12px; color:#6f6f80">
                        @foreach ($hoursByCategory as $h)
                            <span class="me-2">{{ $h->category }} {{ number_format((float) $h->total, 0) }}h</span>
                        @endforeach
                    </div>
                </div>
                <table class="hr-table">
                    <thead><tr><th>编号</th><th>课程</th><th>类别</th><th class="num">课时</th><th>讲师/机构</th><th class="num">开班</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($courses as $c)
                        <tr>
                            <td style="font-family:ui-monospace,Consolas,monospace">{{ $c->code }}</td>
                            <td>{{ $c->title }}</td>
                            <td><span class="hr-badge b-done">{{ $c->category }}</span></td>
                            <td class="num">{{ $c->hours }}</td>
                            <td>{{ $c->provider ?: '内训' }}</td>
                            <td class="num">{{ $c->sessions_count }}</td>
                            <td>
                                <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal" data-bs-target="#courseEdit" data-course="{{ $c->toJson() }}">编辑</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:#8a97a8">还没有课程，用下面的表单建一门。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-5">
            <div class="hr-card mb-3">
                <h3 style="margin:0 0 10px">新建课程</h3>
                <form method="post" action="{{ route('hr.training.courses.store') }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-4"><input class="form-control form-control-sm" name="code" placeholder="TC-001" required></div>
                        <div class="col-8"><input class="form-control form-control-sm" name="title" placeholder="课程名称" required></div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" name="category">
                                <option>安全</option><option>技能</option><option>管理</option><option>合规</option><option>通用</option>
                            </select>
                        </div>
                        <div class="col-3"><input class="form-control form-control-sm" name="hours" type="number" min="1" value="4" placeholder="课时"></div>
                        <div class="col-3"><input class="form-control form-control-sm" name="provider" placeholder="讲师"></div>
                        <div class="col-12"><input class="form-control form-control-sm" name="description" placeholder="课程说明（可空）"></div>
                        <div class="col-12"><button class="btn btn-primary btn-sm">保存课程</button></div>
                    </div>
                </form>
            </div>

            <div class="hr-card">
                <h3 style="margin:0 0 10px">排一期班</h3>
                <form method="post" action="{{ route('hr.training.sessions.store') }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-12">
                            <select class="form-select form-select-sm" name="course_id" required>
                                <option value="">选择课程</option>
                                @foreach ($courses as $c)
                                    <option value="{{ $c->id }}">{{ $c->code }} {{ $c->title }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6"><input class="form-control form-control-sm" type="date" name="start_date" required></div>
                        <div class="col-6"><input class="form-control form-control-sm" type="date" name="end_date"></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="location" placeholder="地点"></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="trainer" placeholder="讲师"></div>
                        <div class="col-6"><input class="form-control form-control-sm" type="number" name="capacity" value="30" placeholder="名额"></div>
                        <div class="col-6 d-flex align-items-end"><button class="btn btn-primary btn-sm w-100">创建开班</button></div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="hr-card mt-3">
        <h3 style="margin:0 0 10px">开班场次</h3>
        <table class="hr-table">
            <thead><tr><th>场次</th><th>课程</th><th>日期</th><th>地点</th><th>讲师</th><th class="num">报名/名额</th><th class="num">通过</th><th>状态</th><th style="width:260px">登记结果</th></tr></thead>
            <tbody>
            @forelse ($sessions as $s)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">{{ $s->session_no }}</td>
                    <td>{{ $s->course?->code }} {{ $s->course?->title }}</td>
                    <td>{{ $s->start_date?->toDateString() }}{{ $s->end_date && $s->end_date->ne($s->start_date) ? ' ~ ' . $s->end_date->toDateString() : '' }}</td>
                    <td>{{ $s->location ?: '—' }}</td>
                    <td>{{ $s->trainer ?: '—' }}</td>
                    <td class="num">{{ $s->enrolled }} / {{ $s->capacity }}</td>
                    <td class="num">{{ $s->passed }}</td>
                    <td><span class="hr-badge {{ $s->status === 'done' ? 'b-done' : 'b-draft' }}">{{ $s->status }}</span></td>
                    <td>
                        <form method="post" action="{{ route('hr.training.sessions.records.store', $s) }}" class="d-flex gap-1">
                            @csrf
                            <select class="form-select form-select-sm" name="employee_id" style="width:110px" required>
                                <option value="">员工</option>
                                @foreach ($employees as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                            <select class="form-select form-select-sm" name="result" style="width:80px">
                                <option value="pass">通过</option>
                                <option value="fail">不通过</option>
                                <option value="pending">待定</option>
                            </select>
                            <input class="form-control form-control-sm" name="score" type="number" min="0" max="100" placeholder="分" style="width:64px">
                            <button class="btn btn-sm btn-outline-primary">登记</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" style="color:#8a97a8">还没有开班记录。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="hr-card mt-3">
        <h3 style="margin:0 0 10px">最近培训结果</h3>
        <table class="hr-table">
            <thead><tr><th>员工</th><th>课程</th><th>场次</th><th>结果</th><th class="num">成绩</th><th>完成时间</th></tr></thead>
            <tbody>
            @forelse ($records as $r)
                <tr>
                    <td>{{ $r->employee?->employee_no }} {{ $r->employee?->name }}</td>
                    <td>{{ $r->session?->course?->title }}</td>
                    <td>{{ $r->session?->session_no }}</td>
                    <td><span class="hr-badge {{ $r->result === 'pass' ? 'b-done' : ($r->result === 'fail' ? 'b-bad' : 'b-draft') }}">{{ $r->result }}</span></td>
                    <td class="num">{{ $r->score ?? '—' }}</td>
                    <td>{{ $r->completed_at?->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="color:#8a97a8">还没有培训记录。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="courseEdit" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <form method="post" id="courseForm">
                @csrf @method('PUT')
                <div class="modal-header"><h5 class="modal-title">编辑课程</h5></div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-12"><label class="form-label">课程名称</label><input class="form-control form-control-sm" name="title" id="ctTitle" required></div>
                        <div class="col-6"><label class="form-label">类别</label><input class="form-control form-control-sm" name="category" id="ctCat" required></div>
                        <div class="col-6"><label class="form-label">课时</label><input class="form-control form-control-sm" type="number" name="hours" id="ctHours" min="1" required></div>
                        <div class="col-12"><label class="form-label">讲师/机构</label><input class="form-control form-control-sm" name="provider" id="ctProv"></div>
                        <div class="col-12">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="ctActive">
                                <label class="form-check-label" for="ctActive">启用</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-primary btn-sm">保存</button></div>
            </form>
        </div></div>
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('courseEdit').addEventListener('show.bs.modal', function (ev) {
        const c = JSON.parse(ev.relatedTarget.getAttribute('data-course') || '{}');
        const form = document.getElementById('courseForm');
        form.action = "{{ url('hr/training/courses') }}/" + c.id;
        document.getElementById('ctTitle').value = c.title || '';
        document.getElementById('ctCat').value = c.category || '';
        document.getElementById('ctHours').value = c.hours || 4;
        document.getElementById('ctProv').value = c.provider || '';
        document.getElementById('ctActive').checked = !!c.is_active;
    });
</script>
@endpush
