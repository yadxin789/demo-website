@extends('mes::layouts.app')

@section('title', $sop ? 'SOP 详情' : '登记 SOP')
@section('sub', $sop ? $sop->code . ' · ' . $sop->title : '给某道工序下发作业指导书')

@section('content')
    @if ($sop)
        <div class="mes-card" style="margin-bottom:14px">
            <div style="display:flex; gap:18px; flex-wrap:wrap; align-items:center">
                <div><span class="mini">SOP 编号</span><br><strong>{{ $sop->code }}</strong></div>
                <div><span class="mini">版本</span><br><strong>{{ $sop->version }}</strong></div>
                <div><span class="mini">状态</span><br>
                    @if ($sop->isEffective())
                        <span class="mes-badge b-done">生效</span>
                    @elseif ($sop->status === 'draft')
                        <span class="mes-badge b-draft">草稿</span>
                    @elseif ($sop->status === 'obsolete')
                        <span class="mes-badge b-bad">作废</span>
                    @else
                        <span class="mes-badge b-released">待生效</span>
                    @endif
                </div>
                <div><span class="mini">生效日期</span><br>{{ $sop->effective_on?->toDateString() ?? '—' }}</div>
                <div><span class="mini">对应工序</span><br>
                    {{ $sop->operation?->routing?->code }} / 第 {{ $sop->operation?->op_no }} 道 · {{ $sop->operation?->op_name }}
                </div>
                <div><span class="mini">工作中心</span><br>{{ $sop->operation?->workCenter?->name ?? '—' }}</div>
                <div style="margin-left:auto">
                    <a class="btn btn-sm btn-light" href="{{ route('mes.sop.download', $sop) }}">下载</a>
                    <a class="btn btn-sm btn-light" href="{{ route('mes.sop.index', ['routing' => $sop->operation?->routing_id]) }}">回列表</a>
                </div>
            </div>
            @if ($sop->content)
                <div style="margin-top:14px; padding:12px; background:#f7f9fc; border-radius:8px; white-space:pre-wrap">{{ $sop->content }}</div>
            @endif
            @if ($sop->hasFile())
                <div class="mini" style="margin-top:10px">附件：{{ $sop->file_name }}</div>
            @endif
        </div>
    @endif

    <div class="mes-card">
        <h3 class="mini" style="margin-bottom:12px">{{ $sop ? '编辑这份 SOP（改版本号即视为升版）' : '新建 SOP' }}</h3>

        <form method="post" enctype="multipart/form-data"
              action="{{ $sop ? route('mes.sop.update', $sop) : route('mes.sop.store') }}">
            @csrf
            @if ($sop) @method('put') @endif

            <div style="display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px">
                <div>
                    <label class="mini">工序 <span style="color:#c00">*</span></label>
                    <select name="routing_operation_id" class="form-select form-select-sm" required>
                        @foreach ($routings as $r)
                            <option disabled>{{ $r->code }} · {{ $r->product?->name }}</option>
                            @foreach ($r->operations as $op)
                                <option value="{{ $op->id }}" @selected($opId === $op->id)>
                                    　第 {{ $op->op_no }} 道 · {{ $op->op_name }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mini">标题 <span style="color:#c00">*</span></label>
                    <input type="text" name="title" class="form-control form-control-sm" required maxlength="120"
                           value="{{ old('title', $sop?->title) }}" placeholder="如：注塑机开机与首件确认">
                </div>
                <div>
                    <label class="mini">SOP 编号（留空自动生成）</label>
                    <input type="text" name="code" class="form-control form-control-sm" maxlength="32"
                           value="{{ old('code', $sop?->code) }}" placeholder="SOP-RT-001-03">
                </div>
                <div>
                    <label class="mini">版本</label>
                    <input type="text" name="version" class="form-control form-control-sm" maxlength="20"
                           value="{{ old('version', $sop?->version ?? 'A/0') }}" placeholder="A/0">
                </div>
                <div>
                    <label class="mini">状态 <span style="color:#c00">*</span></label>
                    <select name="status" class="form-select form-select-sm" required>
                        @foreach (\App\Modules\Mes\Models\SopDocument::STATUS as $k => $label)
                            <option value="{{ $k }}" @selected(old('status', $sop?->status ?? 'active') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mini">生效日期</label>
                    <input type="date" name="effective_on" class="form-control form-control-sm"
                           value="{{ old('effective_on', $sop?->effective_on?->toDateString() ?? now()->toDateString()) }}">
                </div>
                <div style="grid-column:span 2">
                    <label class="mini">正文要点（无附件时现场就看这段）</label>
                    <textarea name="content" class="form-control form-control-sm" rows="5"
                              placeholder="1. 开机前检查…&#10;2. 首件三检…">{{ old('content', $sop?->content) }}</textarea>
                </div>
                <div style="grid-column:span 2">
                    <label class="mini">附件（PDF / 图片 / Word / Excel，≤10MB；替换即覆盖旧附件）</label>
                    <input type="file" name="file" class="form-control form-control-sm">
                    @if ($sop?->hasFile())
                        <div class="mini" style="margin-top:6px">当前附件：{{ $sop->file_name }}</div>
                    @endif
                </div>
            </div>

            <div style="margin-top:14px; display:flex; gap:8px">
                <button class="btn btn-sm btn-primary">{{ $sop ? '保存修改' : '登记 SOP' }}</button>
                <a class="btn btn-sm btn-light" href="{{ route('mes.sop.index') }}">取消</a>
            </div>
        </form>

        @if ($sop)
            <form method="post" action="{{ route('mes.sop.destroy', $sop) }}" style="margin-top:12px"
                  onsubmit="return confirm('确定删除这份 SOP？')">
                @csrf @method('delete')
                <button class="btn btn-sm btn-light">删除这份 SOP</button>
            </form>
        @endif
    </div>
@endsection
