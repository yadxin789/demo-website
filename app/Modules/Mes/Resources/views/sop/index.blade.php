@extends('mes::layouts.app')

@section('title', '工艺文件 SOP')
@section('sub', '按工艺路线逐道工序下发作业指导书，现场可查可下')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">当前路线工序</div>
            <div class="num">{{ $stat['operations'] }}<small>道</small></div>
            <div class="hint">{{ $current?->code ?? '—' }}</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">已下发</div>
            <div class="num">{{ $stat['covered'] }}<small>道</small></div>
            <div class="hint">覆盖率 {{ $stat['coverage'] }}%</div>
        </div>
        <div class="mes-kpi warn">
            <div class="lbl">未下发</div>
            <div class="num">{{ $stat['missing'] }}<small>道</small></div>
            <div class="hint">缺 SOP 的工序，现场无依据可依</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">全库 SOP</div>
            <div class="num">{{ $stat['total_docs'] }}<small>份</small></div>
            <div class="hint">含草稿与作废版本</div>
        </div>
    </div>

    <div class="mes-card">
        <form method="get" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-bottom:12px">
            <label class="mini">工艺路线</label>
            <select name="routing" class="form-select form-select-sm" style="width:320px" onchange="this.form.submit()">
                @foreach ($routings as $r)
                    <option value="{{ $r->id }}" @selected($current && $current->id === $r->id)>
                        {{ $r->code }} · {{ $r->product?->code }} {{ $r->product?->name }}（v{{ $r->version }}，{{ $r->operations->count() }} 道）
                    </option>
                @endforeach
            </select>
            <span class="mini" style="margin-left:auto">切换路线即可看该产品的工序与 SOP 覆盖情况。</span>
        </form>

        <table class="mes-table">
            <thead>
            <tr>
                <th class="num">工序</th>
                <th>工序名称</th>
                <th>工作中心</th>
                <th class="num">标准工时</th>
                <th>SOP 编号</th>
                <th>版本</th>
                <th>状态</th>
                <th>生效日期</th>
                <th>附件</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($current?->operations ?? [] as $op)
                @php($sop = $sops->get($op->id))
                <tr>
                    <td class="num">{{ $op->op_no }}</td>
                    <td>
                        {{ $op->op_name }}
                        @if ($op->inspection_required)<span class="mes-badge b-insp">检</span>@endif
                    </td>
                    <td>{{ $op->workCenter?->name ?? '—' }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) $op->std_minutes, 2), '0'), '.') }} 分</td>
                    <td>{{ $sop?->code ?? '—' }}</td>
                    <td>{{ $sop?->version ?? '—' }}</td>
                    <td>
                        @if ($sop)
                            @if ($sop->isEffective())
                                <span class="mes-badge b-done">生效</span>
                            @elseif ($sop->status === 'draft')
                                <span class="mes-badge b-draft">草稿</span>
                            @elseif ($sop->status === 'obsolete')
                                <span class="mes-badge b-bad">作废</span>
                            @else
                                <span class="mes-badge b-released">待生效</span>
                            @endif
                        @else
                            <span class="mes-badge b-bad">未下发</span>
                        @endif
                    </td>
                    <td>{{ $sop?->effective_on?->toDateString() ?? '—' }}</td>
                    <td>
                        @if ($sop && $sop->hasFile())
                            <span class="mini">{{ $sop->file_name }}</span>
                        @elseif ($sop)
                            <span class="mini">正文</span>
                        @else
                            —
                        @endif
                    </td>
                    <td style="white-space:nowrap">
                        @if ($sop)
                            <a class="btn btn-sm btn-light" href="{{ route('mes.sop.show', $sop) }}">查看</a>
                            <a class="btn btn-sm btn-light" href="{{ route('mes.sop.download', $sop) }}">下载</a>
                            <form method="post" action="{{ route('mes.sop.destroy', $sop) }}" style="display:inline" onsubmit="return confirm('确定删除这份 SOP？')">
                                @csrf @method('delete')
                                <button class="btn btn-sm btn-light">删除</button>
                            </form>
                        @else
                            <a class="btn btn-sm btn-primary" href="{{ route('mes.sop.create', ['op' => $op->id]) }}">登记 SOP</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="mini">当前路线没有工序，先维护工艺路线。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
