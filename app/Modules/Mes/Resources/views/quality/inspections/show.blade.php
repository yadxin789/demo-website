@extends('mes::layouts.app')

@section('title', '检验单 ' . $inspection->inspection_no)
@section('sub', '逐项录入实测值，超差项自动判不合格')

@section('content')
    @php
        $verdicts = $inspection->results->keyBy('item_name');
        $done = $inspection->result !== null;
    @endphp

    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px">
        <div style="font-size:13px; color:#6b7a90">
            工单
            <a href="{{ route('mes.work-orders.show', $inspection->work_order_id) }}" style="color:#16233f; font-weight:600">{{ $inspection->workOrder?->order_no }}</a>
            · {{ $inspection->workOrder?->product?->name }}
            @if ($inspection->operation)
                · 工序 {{ $inspection->operation->op_no }} {{ $inspection->operation->op_name }}
            @endif
            · 模板 <span style="font-family:ui-monospace,Consolas,monospace">{{ $inspection->template?->code }}</span>
        </div>
        <div style="display:flex; gap:8px; align-items:center">
            @if ($inspection->result === 'pass')
                <span class="mes-badge b-done" style="font-size:14px; padding:6px 12px">判定合格</span>
            @elseif ($inspection->result === 'fail')
                <span class="mes-badge b-bad" style="font-size:14px; padding:6px 12px">判定不合格</span>
            @else
                <span class="mes-badge b-released" style="font-size:14px; padding:6px 12px">待检</span>
            @endif
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.quality.inspections.index') }}">返回列表</a>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
            <div style="font-weight:600">检验项 · {{ count($items) }} 项</div>
            <div style="font-size:12px; color:#8a97a8">
                @if ($done)
                    上次由 {{ $inspection->inspector?->name ?? '—' }} 于 {{ $inspection->inspected_at?->format('Y-m-d H:i') }} 判定，可重检
                @else
                    实测值留空即判不合格
                @endif
            </div>
        </div>

        <form method="post" action="{{ route('mes.quality.inspections.submit', $inspection) }}">
            @csrf
            <table class="mes-table" style="font-size:13px">
                <thead>
                <tr>
                    <th style="width:24%">检验项</th>
                    <th style="width:22%">标准 / 范围</th>
                    <th style="width:22%">实测值</th>
                    <th style="width:32%">判定</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($items as $idx => $item)
                    @php
                        $name = (string) ($item['name'] ?? ('项目 ' . ($idx + 1)));
                        $prev = $verdicts->get($name);
                        $val = $prev ? (string) $prev->actual_value : (string) ($previous[$name] ?? ($item['expect'] ?? ''));
                    @endphp
                    <tr>
                        <td>{{ $name }}</td>
                        <td style="font-family:ui-monospace,Consolas,monospace; color:#4a5568">
                            {{ $service->stdText($item) }}
                            @if (! empty($item['unit']))<span style="color:#8a97a8">{{ $item['unit'] }}</span>@endif
                            @if (! empty($item['expect']))
                                <div style="font-size:11px; color:#8a97a8">合格样本 {{ $item['expect'] }}</div>
                            @endif
                        </td>
                        <td>
                            <input name="actual[{{ $idx }}]" class="form-control form-control-sm" value="{{ $val }}" placeholder="实测值">
                        </td>
                        <td>
                            @if ($prev)
                                <span class="mes-badge {{ $prev->verdict === 'ok' ? 'b-done' : 'b-bad' }}">{{ $prev->verdict === 'ok' ? '合格' : '超差' }}</span>
                            @else
                                <span style="color:#a8b3c1">未判定</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px">
                <div style="font-size:12px; color:#8a97a8">
                    提交后按规则自动出整体结论；判不合格就去不良记录页登记处置方式。
                </div>
                <button class="btn btn-primary">{{ $done ? '重新检验并覆盖结果' : '提交检验结果' }}</button>
            </div>
        </form>
    </div>

    @if ($done && $inspection->result === 'fail')
        <div class="mes-card" style="margin-top:14px; border-left:4px solid #c92a2a">
            <div style="font-weight:600; color:#a61e4d; margin-bottom:6px">这张单判了不合格</div>
            <div style="font-size:13px; color:#4a5568">
                下一步到
                <a href="{{ route('mes.quality.defects.index') }}" style="color:#16233f; font-weight:600">不良记录</a>
                登记不良数量与处置方式（返工 / 报废），返工可一键转成一张返工工单。
            </div>
        </div>
    @endif
@endsection
