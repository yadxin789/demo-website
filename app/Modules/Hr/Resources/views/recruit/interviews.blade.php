@extends('hr::layouts.app')

@section('title', '面试记录')
@section('sub', '华瑞集团（控股）· 谁面了谁、第几轮、几分、什么结论')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi">
            <div class="lbl">面试场次</div>
            <div class="num">{{ $kpi['total'] }}</div>
            <div class="hint">累计记录</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">通过</div>
            <div class="num">{{ $kpi['pass'] }}</div>
            <div class="hint">建议进入下一轮</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">未通过</div>
            <div class="num">{{ $kpi['fail'] }}</div>
            <div class="hint">已反馈淘汰</div>
        </div>
        <div class="hr-kpi purple">
            <div class="lbl">平均评分</div>
            <div class="num">{{ $kpi['avg'] }}</div>
            <div class="hint">满分 10 分</div>
        </div>
    </div>

    <div class="hr-card">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <h3 style="margin:0">面试明细 {{ $rows->total() }} 条</h3>
            <div class="d-flex gap-2 flex-wrap">
                <form class="d-flex gap-2" method="get" action="{{ route('hr.recruit.interviews') }}" style="margin:0">
                    <select class="form-select form-select-sm" name="round" onchange="this.form.submit()" style="width:110px">
                        <option value="0">全部轮次</option>
                        <option value="1" @selected($round === 1)>第 1 轮</option>
                        <option value="2" @selected($round === 2)>第 2 轮</option>
                    </select>
                    <select class="form-select form-select-sm" name="result" onchange="this.form.submit()" style="width:120px">
                        <option value="">全部结论</option>
                        @foreach ($resultText as $k => $v)
                            <option value="{{ $k }}" @selected($result === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </form>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#itvModal" type="button">录面试</button>
            </div>
        </div>

        <table class="hr-table">
            <thead>
            <tr><th>面试时间</th><th>候选人</th><th>应聘岗位</th><th class="num">轮次</th><th>面试官</th><th class="num">评分</th><th>结论</th><th>反馈</th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td style="font-size:12px">{{ $row->interviewed_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td><b>{{ $row->candidate?->name ?? '—' }}</b></td>
                    <td>
                        {{ $row->candidate?->requisition?->position?->name ?? '—' }}
                        <span style="color:#9a9aad; font-size:11px">{{ $stageText[$row->candidate?->stage] ?? '' }}</span>
                    </td>
                    <td class="num">第 {{ $row->round }} 轮</td>
                    <td>{{ $row->interviewer?->name ?? '未指定' }}</td>
                    <td class="num" style="font-weight:600">{{ $row->score }}</td>
                    <td>
                        <span class="hr-badge {{ $row->result === 'pass' ? 'b-done' : ($row->result === 'fail' ? 'b-bad' : 'b-progress') }}">
                            {{ $resultText[$row->result] ?? $row->result }}
                        </span>
                    </td>
                    <td style="font-size:12px; color:#6f6f80; max-width:260px">{{ $row->feedback ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="hr-empty">这个筛选条件下没有面试记录。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>

    <div class="modal fade" id="itvModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('hr.recruit.interviews.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">录入面试记录</h5>
                    <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">候选人</label>
                        <select class="form-select" name="candidate_id" required>
                            <option value="">选择候选人</option>
                            @foreach ($candidates as $c)
                                <option value="{{ $c->id }}">{{ $c->name }} · {{ $c->requisition?->position?->name }}（{{ $stageText[$c->stage] ?? '' }}）</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row g-2">
                        <div class="col-4">
                            <label class="form-label">轮次</label>
                            <select class="form-select" name="round">
                                <option value="1">第 1 轮</option>
                                <option value="2">第 2 轮</option>
                                <option value="3">第 3 轮</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label">评分</label>
                            <input class="form-control" name="score" type="number" min="0" max="10" step="0.5" value="8" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">结论</label>
                            <select class="form-select" name="result">
                                <option value="pass">通过</option>
                                <option value="hold">待定</option>
                                <option value="fail">未通过</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label">面试官</label>
                        <select class="form-select" name="interviewer_id">
                            <option value="">不指定</option>
                            @foreach ($interviewers as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mt-2">
                        <label class="form-label">反馈</label>
                        <input class="form-control" name="feedback" maxlength="200" placeholder="例：表达清晰，专业基础扎实，建议进入下一轮">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">取消</button>
                    <button class="btn btn-primary" type="submit">保存</button>
                </div>
            </form>
        </div>
    </div>
@endsection
