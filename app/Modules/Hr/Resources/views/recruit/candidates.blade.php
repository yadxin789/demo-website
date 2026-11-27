@extends('hr::layouts.app')

@section('title', '候选人')
@section('sub', '华瑞集团（控股）· 五个阶段一张漏斗，点一行就能往下推')

@section('content')
    @php($stages = ['screening' => '#868e96', 'interview' => '#4c6ef5', 'offer' => '#0ca678', 'onboard' => '#5f3dc4', 'reject' => '#d6336c'])
    <div class="hr-card mb-3">
        <h3>招聘漏斗（共 {{ $funnelTotal }} 人）</h3>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px">
            @foreach ($stageText as $k => $label)
                @php($n = (int) ($funnel[$k] ?? 0))
                <div style="flex:1; min-width:120px">
                    <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px">
                        <span>{{ $label }}</span>
                        <b>{{ $n }}</b>
                    </div>
                    <div style="height:10px; background:#eceaf6; border-radius:6px; overflow:hidden">
                        <span style="display:block; height:10px; background:{{ $stages[$k] }}; width: {{ (int) round($n / $funnelTotal * 100) }}%"></span>
                    </div>
                </div>
            @endforeach
        </div>
        <div style="color:#9a9aad; font-size:12px; margin-top:10px">
            从"简历筛选"一路推到"已入职"，入职时会把需求单的已招人数 +1。
        </div>
    </div>

    <div class="hr-card">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h3 style="margin:0">候选人 {{ $rows->total() }} 人</h3>
            <div class="d-flex gap-2 flex-wrap">
                <form class="d-flex gap-2" method="get" action="{{ route('hr.recruit.candidates') }}" style="margin:0">
                    <select class="form-select form-select-sm" name="stage" onchange="this.form.submit()" style="width:120px">
                        <option value="">全部阶段</option>
                        @foreach ($stageText as $k => $v)
                            <option value="{{ $k }}" @selected($stage === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                    <select class="form-select form-select-sm" name="req" onchange="this.form.submit()" style="width:170px">
                        <option value="0">全部需求</option>
                        @foreach ($requisitions as $req)
                            <option value="{{ $req->id }}" @selected($reqId === $req->id)>{{ $req->req_no }} · {{ $req->position?->name }}</option>
                        @endforeach
                    </select>
                    <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="搜姓名" style="width:120px">
                    <button class="btn btn-sm btn-outline-secondary" type="submit">筛</button>
                </form>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#candModal" type="button">录候选人</button>
            </div>
        </div>

        <table class="hr-table">
            <thead>
            <tr><th>姓名</th><th>应聘岗位</th><th>来源</th><th class="num">年限</th><th>阶段</th><th class="num">面试</th><th>简历摘要</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $c)
                <tr>
                    <td><b>{{ $c->name }}</b></td>
                    <td>{{ $c->requisition?->position?->name ?? '—' }} <span style="color:#9a9aad; font-size:11px">{{ $c->requisition?->req_no }}</span></td>
                    <td>{{ $c->source }}</td>
                    <td class="num">{{ $c->years }} 年</td>
                    <td>
                        <span class="hr-badge {{ $c->stage === 'onboard' ? 'b-done' : ($c->stage === 'reject' ? 'b-bad' : 'b-progress') }}">
                            {{ $stageText[$c->stage] ?? $c->stage }}
                        </span>
                    </td>
                    <td class="num">{{ $c->interviews_count }}</td>
                    <td style="font-size:12px; color:#6f6f80">{{ $c->resume_summary ?? '—' }}</td>
                    <td>
                        <div class="d-flex gap-1">
                            @if (isset($nextStage[$c->stage]))
                                <form method="post" action="{{ route('hr.recruit.candidates.advance', $c->id) }}" style="margin:0">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-primary" type="submit">
                                        推到「{{ $stageText[$nextStage[$c->stage]] }}」
                                    </button>
                                </form>
                            @endif
                            @if ($c->stage !== 'reject' && $c->stage !== 'onboard')
                                <form method="post" action="{{ route('hr.recruit.candidates.reject', $c->id) }}" style="margin:0"
                                      onsubmit="return confirm('标记淘汰后不能再推进，确定？')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">淘汰</button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="hr-empty">这个筛选条件下没有候选人。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>

    <div class="modal fade" id="candModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('hr.recruit.candidates.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">录入候选人</h5>
                    <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">应聘需求</label>
                        <select class="form-select" name="requisition_id" required>
                            <option value="">选择在招需求</option>
                            @foreach ($requisitions as $req)
                                <option value="{{ $req->id }}">{{ $req->req_no }} · {{ $req->position?->name }}（{{ $req->hired }}/{{ $req->headcount }}）</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">姓名</label>
                            <input class="form-control" name="name" maxlength="20" required placeholder="例：花清和">
                        </div>
                        <div class="col-6">
                            <label class="form-label">来源</label>
                            <select class="form-select" name="source">
                                <option value="内推">内推</option>
                                <option value="BOSS">BOSS</option>
                                <option value="猎头">猎头</option>
                                <option value="校招">校招</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">手机</label>
                            <input class="form-control" name="phone" maxlength="20">
                        </div>
                        <div class="col-6">
                            <label class="form-label">邮箱</label>
                            <input class="form-control" name="email" type="email" maxlength="60">
                        </div>
                        <div class="col-4">
                            <label class="form-label">工作年限</label>
                            <input class="form-control" name="years" type="number" min="0" max="40" value="3" required>
                        </div>
                        <div class="col-8">
                            <label class="form-label">简历摘要</label>
                            <input class="form-control" name="resume_summary" maxlength="120" placeholder="例：熟悉制造业流程，有同类岗位经验">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">取消</button>
                    <button class="btn btn-primary" type="submit">录入</button>
                </div>
            </form>
        </div>
    </div>
@endsection
