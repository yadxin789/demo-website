@extends('hr::layouts.app')

@section('title', '编制缺口')
@section('sub', '华瑞集团（控股）· 部门 × 岗位的编制与在职对照')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi purple">
            <div class="lbl">编制行数</div>
            <div class="num">{{ $kpi['rows'] }}</div>
            <div class="hint">部门 × 岗位</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">编制合计</div>
            <div class="num">{{ $kpi['planned'] }}</div>
            <div class="hint">人力规划总量</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">在职合计</div>
            <div class="num">{{ $kpi['filled'] }}</div>
            <div class="hint">不含离职</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">缺口合计</div>
            <div class="num">{{ $kpi['gap'] }}</div>
            <div class="hint">需要在招补上</div>
        </div>
    </div>

    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">部门</label>
                <select class="form-select form-select-sm" name="dept">
                    <option value="">全部部门</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected((int) $deptId === $d->id)>{{ $d->code }} {{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <div class="form-check mt-1">
                    <input class="form-check-input" id="gap" name="gap" type="checkbox" value="1" @checked($onlyGap)>
                    <label class="form-check-label" for="gap">只看有缺口的</label>
                </div>
            </div>
            <div class="col-md-5 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">筛选</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.org.headcounts.index') }}">重置</a>
                <button class="btn btn-sm btn-outline-primary ms-auto" type="button" data-bs-toggle="modal"
                        data-bs-target="#hcModal">新增编制</button>
            </div>
        </form>
    </div>

    <div class="hr-card">
        <h3>编制明细（缺口大的排前面）</h3>
        <table class="hr-table">
            <thead>
            <tr>
                <th>部门</th><th>岗位</th><th>职级</th>
                <th class="num">编制</th><th class="num">在职</th><th class="num">缺口</th>
                <th>缺口占比</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $h)
                @php($gap = max(0, $h->planned - $h->filled))
                @php($pct = $h->planned > 0 ? min(100, round($gap / $h->planned * 100)) : 0)
                <tr>
                    <td>{{ $h->department?->name ?? '—' }}</td>
                    <td>{{ $h->position?->code }} {{ $h->position?->name }}</td>
                    <td>{{ $h->position?->grade ?? '—' }}</td>
                    <td class="num">{{ $h->planned }}</td>
                    <td class="num">{{ $h->filled }}</td>
                    <td class="num">
                        @if ($gap > 0)
                            <span style="color:#d6336c; font-weight:600">{{ $gap }}</span>
                        @else
                            <span style="color:#2f9e44">0</span>
                        @endif
                    </td>
                    <td style="min-width:120px">
                        <div class="hr-progress"><span style="width: {{ $pct }}%; background: {{ $gap > 0 ? '#d6336c' : '#2f9e44' }}"></span></div>
                    </td>
                    <td>
                        <form method="post" action="{{ route('hr.org.headcounts.update', $h->id) }}" class="d-flex gap-1" style="margin:0">
                            @csrf @method('put')
                            <input class="form-control form-control-sm" name="planned" type="number" min="0" max="999"
                                   value="{{ $h->planned }}" style="width:76px">
                            <button class="btn btn-sm btn-outline-primary" type="submit">改编制</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="hr-empty">没有匹配的编制行。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="hcModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" action="{{ route('hr.org.headcounts.store') }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">新增编制</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">部门</label>
                                <select class="form-select" name="department_id" required>
                                    <option value="">选择部门</option>
                                    @foreach ($departments as $d)
                                        <option value="{{ $d->id }}">{{ $d->code }} {{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">岗位</label>
                                <select class="form-select" name="position_id" required>
                                    <option value="">选择岗位</option>
                                    @foreach ($positions as $p)
                                        <option value="{{ $p->id }}">{{ $p->code }} {{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">编制数</label>
                                <input class="form-control" name="planned" type="number" min="0" max="999" value="1" required>
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
