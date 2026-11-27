@extends('hr::layouts.app')

@section('title', '招聘需求')
@section('sub', '华瑞集团（控股）· 需求单 → 候选人 → 面试 → 入职，一条线跑通')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi">
            <div class="lbl">需求单</div>
            <div class="num">{{ $kpi['total'] }}</div>
            <div class="hint">累计开单</div>
        </div>
        <div class="hr-kpi purple">
            <div class="lbl">在招</div>
            <div class="num">{{ $kpi['open'] }}</div>
            <div class="hint">状态为 open</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">已入职</div>
            <div class="num">{{ $kpi['hired'] }}</div>
            <div class="hint">计划 {{ $kpi['planned'] }} 人</div>
        </div>
        <div class="hr-kpi warn">
            <div class="lbl">缺口</div>
            <div class="num">{{ $kpi['gap'] }}</div>
            <div class="hint">还没有人到岗</div>
        </div>
    </div>

    <div class="hr-card">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h3 style="margin:0">需求列表</h3>
            <div class="d-flex gap-2">
                <form method="get" action="{{ route('hr.recruit.requisitions') }}" style="margin:0">
                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()" style="width:130px">
                        <option value="">全部状态</option>
                        @foreach ($statusText as $k => $v)
                            <option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </form>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#reqModal" type="button">开需求单</button>
            </div>
        </div>

        <table class="hr-table">
            <thead>
            <tr><th>单号</th><th>岗位</th><th>用人部门</th><th class="num">计划</th><th class="num">已入职</th><th>进度</th><th>紧急度</th><th>状态</th><th>发起日</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $r->req_no }}</td>
                    <td>{{ $r->position?->name ?? '—' }}</td>
                    <td>{{ $r->department?->name ?? '—' }}</td>
                    <td class="num">{{ $r->headcount }}</td>
                    <td class="num">{{ $r->hired }}</td>
                    <td style="min-width:96px">
                        <div class="hr-progress"><span style="width: {{ (int) min(100, $r->headcount > 0 ? $r->hired / $r->headcount * 100 : 0) }}%"></span></div>
                        <span style="font-size:11px; color:#9a9aad">{{ $r->hired }}/{{ $r->headcount }}</span>
                    </td>
                    <td>
                        @if ($r->urgency === 'high')
                            <span class="hr-badge b-bad">{{ $urgencyText[$r->urgency] }}</span>
                        @elseif ($r->urgency === 'low')
                            <span class="hr-badge b-draft">{{ $urgencyText[$r->urgency] }}</span>
                        @else
                            <span class="hr-badge b-progress">{{ $urgencyText[$r->urgency] }}</span>
                        @endif
                    </td>
                    <td>
                        <span class="hr-badge {{ $r->status === 'open' ? 'b-progress' : 'b-done' }}">{{ $statusText[$r->status] ?? $r->status }}</span>
                    </td>
                    <td style="font-size:12px">{{ $r->raised_on?->format('Y-m-d') ?? '—' }}</td>
                    <td>
                        @if ($r->status === 'open')
                            <form method="post" action="{{ route('hr.recruit.requisitions.close', $r->id) }}" style="margin:0"
                                  onsubmit="return confirm('关闭后不再接收新候选人，确定？')">
                                @csrf
                                <button class="btn btn-sm btn-outline-secondary" type="submit">关闭</button>
                            </form>
                        @else
                            <span style="color:#9a9aad; font-size:12px">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="hr-empty">这个状态下没有需求单。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="reqModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('hr.recruit.requisitions.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">开招聘需求单</h5>
                    <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">招聘岗位</label>
                        <select class="form-select" name="position_id" required>
                            <option value="">选择岗位</option>
                            @foreach ($positions as $p)
                                <option value="{{ $p->id }}">{{ $p->code }} · {{ $p->name }}（{{ $p->department?->name }}）</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">招聘人数</label>
                            <input class="form-control" name="headcount" type="number" min="1" max="50" value="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">紧急度</label>
                            <select class="form-select" name="urgency">
                                <option value="normal">正常</option>
                                <option value="high">紧急</option>
                                <option value="low">不紧急</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label">备注</label>
                        <input class="form-control" name="remark" maxlength="120" placeholder="例：业务扩张，优先补人">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">取消</button>
                    <button class="btn btn-primary" type="submit">开单</button>
                </div>
            </form>
        </div>
    </div>
@endsection
