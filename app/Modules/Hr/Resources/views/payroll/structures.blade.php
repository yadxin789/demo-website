@extends('hr::layouts.app')

@section('title', '薪资结构')
@section('sub', '岗位四段薪资档：基本 / 岗位 / 绩效 / 补贴')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi purple">
            <div class="lbl">岗位数</div>
            <div class="num">{{ $totals['positions'] }}</div>
            <div class="hint">全集团岗位</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">已设薪资档</div>
            <div class="num">{{ $totals['covered'] }}</div>
            <div class="hint">没有档位的岗位不出工资条</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">月薪标准合计</div>
            <div class="num" style="font-size:18px">{{ number_format($totals['monthly'], 0) }}</div>
            <div class="hint">四段相加（不含加班）</div>
        </div>
    </div>

    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">岗位</label>
                <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="岗位编码 / 名称">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">部门</label>
                <select class="form-select form-select-sm" name="dept">
                    <option value="">全部部门</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected($deptId === $d->id)>{{ $d->code }} {{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">筛选</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.payroll.structures') }}">重置</a>
            </div>
        </form>
    </div>

    <div class="hr-card">
        <h3>岗位薪资档（{{ $positions->count() }} 个岗位）</h3>
        <table class="hr-table">
            <thead>
            <tr><th>岗位</th><th>部门</th><th>职级</th><th class="num">基本</th><th class="num">岗位</th><th class="num">绩效</th><th class="num">补贴</th><th class="num">四段合计</th><th>生效日</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($positions as $p)
                @php($s = $structures[$p->id] ?? null)
                <tr>
                    <td>{{ $p->code }} {{ $p->name }}</td>
                    <td>{{ $p->department?->name ?? '—' }}</td>
                    <td>{{ $p->grade ?? '—' }}</td>
                    <td class="num">{{ $s ? number_format((float) $s->base, 0) : '—' }}</td>
                    <td class="num">{{ $s ? number_format((float) $s->post, 0) : '—' }}</td>
                    <td class="num">{{ $s ? number_format((float) $s->performance, 0) : '—' }}</td>
                    <td class="num">{{ $s ? number_format((float) $s->allowance, 0) : '—' }}</td>
                    <td class="num" style="font-weight:600">
                        {{ $s ? number_format((float) $s->base + (float) $s->post + (float) $s->performance + (float) $s->allowance, 0) : '未设' }}
                    </td>
                    <td style="font-size:12px">{{ $s?->effective_date?->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="modal" data-bs-target="#stModal"
                                onclick='openStructure({{ json_encode([
                                    "position_id" => $p->id,
                                    "position_text" => $p->code . " " . $p->name,
                                    "id" => $s?->id,
                                    "base" => $s ? (float) $s->base : "",
                                    "post" => $s ? (float) $s->post : "",
                                    "performance" => $s ? (float) $s->performance : "",
                                    "allowance" => $s ? (float) $s->allowance : "",
                                    "effective_date" => $s?->effective_date?->format("Y-m-d"),
                                ], JSON_UNESCAPED_UNICODE) }})'>{{ $s ? '改档' : '设档' }}</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="hr-empty">没有匹配的岗位。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="stModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="stForm" action="{{ route('hr.payroll.structures.store') }}">
                    @csrf
                    <input type="hidden" name="_method" id="stMethod" value="post">
                    <input type="hidden" name="position_id" id="stPosId">
                    <div class="modal-header">
                        <h5 class="modal-title" id="stTitle">设置薪资档</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2" style="color:#6f6f80; font-size:13px">岗位：<b id="stPosText"></b></div>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">基本工资</label>
                                <input class="form-control" name="base" id="stBase" type="number" step="0.01" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">岗位工资</label>
                                <input class="form-control" name="post" id="stPost" type="number" step="0.01" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">绩效工资（标准档）</label>
                                <input class="form-control" name="performance" id="stPerf" type="number" step="0.01" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">补贴</label>
                                <input class="form-control" name="allowance" id="stAllow" type="number" step="0.01" min="0" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">生效日</label>
                                <input class="form-control" name="effective_date" id="stDate" type="date">
                            </div>
                        </div>
                        <div class="form-text mt-2">绩效实发 = 标准档 × 系数（试用 0.8，其余按工号 0.9~1.1 错开）。</div>
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
    function openStructure(p) {
        const form = document.getElementById('stForm');
        const method = document.getElementById('stMethod');
        const title = document.getElementById('stTitle');

        document.getElementById('stPosId').value = p.position_id;
        document.getElementById('stPosText').textContent = p.position_text;

        if (p.id) {
            title.textContent = '修改薪资档';
            form.action = "{{ url('hr/payroll/structures') }}/" + p.id;
            method.value = 'put';
        } else {
            title.textContent = '设置薪资档';
            form.action = "{{ route('hr.payroll.structures.store') }}";
            method.value = 'post';
        }

        document.getElementById('stBase').value = p.base;
        document.getElementById('stPost').value = p.post;
        document.getElementById('stPerf').value = p.performance;
        document.getElementById('stAllow').value = p.allowance;
        document.getElementById('stDate').value = p.effective_date ?? '{{ now()->toDateString() }}';
    }
</script>
@endpush
