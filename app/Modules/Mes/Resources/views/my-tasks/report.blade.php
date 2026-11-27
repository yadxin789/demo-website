@extends('mes::layouts.app')

@section('title', '报工 · ' . $op->op_name)
@section('sub', '手机端也能用的大按钮报工页')

@push('styles')
<style>
    .mr-wrap { max-width: 560px; }
    .mr-input { font-size: 20px; height: 56px; text-align: center; }
    .mr-label { font-size: 13px; font-weight: 600; color: #16233f; margin-bottom: 6px; }
    .mr-submit { height: 56px; font-size: 17px; width: 100%; }
</style>
@endpush

@section('content')
    <div class="mr-wrap">
        <div class="mes-card" style="margin-bottom:14px">
            <div style="font-family:ui-monospace,Consolas,monospace; font-size:13px; color:#6b7a90">
                <a href="{{ route('mes.work-orders.show', $order) }}" style="color:#6b7a90">{{ $order->order_no }}</a>
                · {{ $order->product?->name }}
                · {{ $op->workCenter?->name }}
            </div>
            <div style="font-size:24px; font-weight:700; color:#16233f; margin:6px 0 2px">
                {{ $op->op_no }} {{ $op->op_name }}
            </div>
            <div style="font-size:13px; color:#4a5568">
                计划 {{ rtrim(rtrim(number_format((float) $op->plan_qty, 3), '0'), '.') }} 件
                · 已做 {{ rtrim(rtrim(number_format((float) $op->done_qty, 3), '0'), '.') }} 件
                · 还剩 <b style="color:#a25c00">{{ rtrim(rtrim(number_format($remain, 3), '0'), '.') }}</b> 件
                @if ($op->status === 'in_progress') · <span class="mes-badge b-progress">进行中</span> @endif
            </div>
        </div>

        <form method="post" action="{{ route('mes.work-orders.operations.report', [$order, $op]) }}" class="mes-card">
            @csrf
            <input type="hidden" name="from" value="my">

            <div class="row g-3">
                <div class="col-6">
                    <div class="mr-label">合格数</div>
                    <input name="ok_qty" type="number" step="1" min="0" class="form-control mr-input"
                           value="{{ (int) $remain }}" inputmode="numeric">
                </div>
                <div class="col-6">
                    <div class="mr-label">不良数</div>
                    <input name="defect_qty" type="number" step="1" min="0" class="form-control mr-input" value="0" inputmode="numeric">
                </div>
                <div class="col-6">
                    <div class="mr-label">工时（分钟）</div>
                    <input name="minutes" type="number" step="1" min="0" max="1440" class="form-control mr-input" value="60" inputmode="numeric">
                </div>
                <div class="col-6">
                    <div class="mr-label">班次</div>
                    <select name="shift_id" class="form-select mr-input" required>
                        <option value="">请选择</option>
                        @foreach ($shifts as $s)
                            <option value="{{ $s->id }}" @selected($loop->first)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <div class="mr-label">备注（可不填）</div>
                    <input name="remark" class="form-control" style="height:44px" placeholder="换模、缺料、设备异常等">
                </div>
            </div>

            <div style="font-size:12px; color:#8a97a8; margin:12px 0 0; line-height:1.8">
                合格数与不良数不能都是 0；累计完成不允许超过计划的 120%。<br>
                提交后这道工序的完成数会自动累加，工单按最慢的那道工序计算总进度。
            </div>

            <div style="display:flex; gap:10px; margin-top:16px">
                <a class="btn btn-outline-secondary" style="height:56px; line-height:44px; min-width:96px" href="{{ route('mes.my-tasks.index') }}">返回</a>
                <button class="btn btn-primary mr-submit">提交报工</button>
            </div>
        </form>

        @if ($recent->isNotEmpty())
            <div class="mes-card" style="margin-top:14px">
                <div style="font-weight:600; margin-bottom:8px">这道工序最近 {{ $recent->count() }} 笔</div>
                <table class="mes-table" style="font-size:13px">
                    <thead><tr><th>时间</th><th class="num">合格</th><th class="num">不良</th><th class="num">工时</th></tr></thead>
                    <tbody>
                    @foreach ($recent as $r)
                        <tr>
                            <td style="font-family:ui-monospace,Consolas,monospace">{{ $r->reported_at?->format('m-d H:i') }}</td>
                            <td class="num" style="color:#227a39">{{ rtrim(rtrim(number_format((float) $r->ok_qty, 3), '0'), '.') }}</td>
                            <td class="num" style="color:{{ (float) $r->defect_qty > 0 ? '#c92a2a' : '#a8b3c1' }}">{{ rtrim(rtrim(number_format((float) $r->defect_qty, 3), '0'), '.') }}</td>
                            <td class="num">{{ $r->minutes }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
