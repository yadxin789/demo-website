@extends('mes::layouts.app')

@section('title', '领料单 ' . $issue->issue_no)
@section('sub', '按行填实发数量，库存实时扣减')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px">
        <div style="font-size:13px; color:#6b7a90">
            工单
            <a href="{{ route('mes.work-orders.show', $issue->work_order_id) }}" style="color:#16233f; font-weight:600">
                {{ $issue->workOrder?->order_no }}
            </a>
            · {{ $issue->workOrder?->product?->name }}
            @if ($issue->issued_at)
                · 首次发料 {{ $issue->issued_at->format('Y-m-d H:i') }}
            @endif
            @if ($issue->issuer)
                by {{ $issue->issuer->name }}
            @endif
        </div>
        <div style="display:flex; gap:8px">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.issues.index') }}">返回列表</a>
        </div>
    </div>

    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">需求总量</div>
            <div class="num">{{ rtrim(rtrim(number_format($summary['required'], 3), '0'), '.') }}</div>
            <div class="hint">{{ $summary['lines'] }} 行物料</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">已发总量</div>
            <div class="num">{{ rtrim(rtrim(number_format($summary['issued'], 3), '0'), '.') }}</div>
            <div class="hint">发料率 {{ $summary['rate'] }}%</div>
        </div>
        <div class="mes-kpi orange">
            <div class="lbl">料本金额</div>
            <div class="num">¥{{ number_format($summary['amount'], 2) }}</div>
            <div class="hint">按标准成本估算</div>
        </div>
        <div class="mes-kpi {{ $issue->status === 'issued' ? 'ok' : '' }}">
            <div class="lbl">单据状态</div>
            <div class="num" style="font-size:22px">
                {{ ['draft' => '待发料', 'partial' => '部分发料', 'issued' => '已发齐'][$issue->status] ?? $issue->status }}
            </div>
            <div class="hint">全部行发满才算发齐</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px">
            <div style="font-weight:600">用料明细</div>
            <div style="font-size:12px; color:#8a97a8">
                @if ($issue->status === 'issued')
                    单据已发齐，数量不可再改
                @else
                    留空表示这行这次不发；填了数量提交后会扣库存
                @endif
            </div>
        </div>

        <form method="post" action="{{ route('mes.issues.issue', $issue) }}">
            @csrf
            <table class="mes-table" style="font-size:13px">
                <thead>
                <tr>
                    <th>物料编码</th>
                    <th>名称 / 规格</th>
                    <th class="num">需求</th>
                    <th class="num">已发</th>
                    <th class="num">未发</th>
                    <th class="num">现有库存</th>
                    <th style="width:140px">本次实发</th>
                    <th>状态</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($issue->lines as $line)
                    @php
                        $req = (float) $line->required_qty;
                        $got = (float) $line->issued_qty;
                        $remain = max(0, $req - $got);
                        $stock = (float) ($line->material?->stock_qty ?? 0);
                        $short = $stock < $remain;
                    @endphp
                    <tr>
                        <td style="font-family:ui-monospace,Consolas,monospace">{{ $line->material?->code }}</td>
                        <td>
                            {{ $line->material?->name }}
                            <div style="color:#8a97a8; font-size:12px">{{ $line->material?->spec ?: '—' }}</div>
                        </td>
                        <td class="num">{{ rtrim(rtrim(number_format($req, 3), '0'), '.') }} {{ $line->material?->unit }}</td>
                        <td class="num" style="color:{{ $got > 0 ? '#227a39' : '#a8b3c1' }}">
                            {{ rtrim(rtrim(number_format($got, 3), '0'), '.') }}
                        </td>
                        <td class="num" style="color:{{ $remain > 0 ? '#c92a2a' : '#8a97a8' }}">
                            {{ rtrim(rtrim(number_format($remain, 3), '0'), '.') }}
                        </td>
                        <td class="num" style="color:{{ $short ? '#c92a2a' : '#16233f' }}">
                            {{ rtrim(rtrim(number_format($stock, 3), '0'), '.') }}
                            @if ($short)<div style="font-size:11px">不够</div>@endif
                        </td>
                        <td>
                            @if ($issue->status === 'issued' || $remain <= 0)
                                <span style="color:#a8b3c1">—</span>
                            @else
                                <input name="qty[{{ $line->id }}]" type="number" step="0.001" min="0" max="{{ $remain }}"
                                       class="form-control form-control-sm" placeholder="{{ rtrim(rtrim(number_format($remain, 3), '0'), '.') }}">
                            @endif
                        </td>
                        <td>
                            <span class="mes-badge {{ $line->status === 'issued' ? 'b-done' : ($line->status === 'partial' ? 'b-progress' : 'b-draft') }}">
                                {{ ['pending' => '待发', 'partial' => '部分', 'issued' => '已发'][$line->status] ?? $line->status }}
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            @if ($issue->status !== 'issued')
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px">
                    <div style="font-size:12px; color:#8a97a8">
                        提交后按行扣减物料库存；库存不够的行会整行跳过并列在结果里。
                    </div>
                    <button class="btn btn-primary">提交发料</button>
                </div>
            @endif
        </form>
    </div>
@endsection
