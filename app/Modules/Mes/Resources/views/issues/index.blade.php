@extends('mes::layouts.app')

@section('title', '物料领用')
@section('sub', '按工单算料、按行发料，库存实时扣减')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">待发料</div>
            <div class="num">{{ $stat['draft'] }}</div>
            <div class="hint">还没动过的领料单</div>
        </div>
        <div class="mes-kpi orange">
            <div class="lbl">部分发料</div>
            <div class="num">{{ $stat['partial'] }}</div>
            <div class="hint">发了但没发齐</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">已发齐</div>
            <div class="num">{{ $stat['issued'] }}</div>
            <div class="hint">可以开工了</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">待发行数</div>
            <div class="num">{{ $stat['lines'] }}</div>
            <div class="hint">未发齐单里的明细行</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px">
            <form method="get" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
                <input name="q" value="{{ $q }}" class="form-control form-control-sm" style="width:200px" placeholder="领料单号 / 工单号">
                <select name="status" class="form-select form-select-sm" style="width:130px">
                    <option value="">全部状态</option>
                    <option value="draft" @selected($status === 'draft')>待发料</option>
                    <option value="partial" @selected($status === 'partial')>部分发料</option>
                    <option value="issued" @selected($status === 'issued')>已发齐</option>
                </select>
                <button class="btn btn-sm btn-outline-secondary">查询</button>
                @if ($q !== '' || $status !== '')
                    <a href="{{ route('mes.issues.index') }}" class="btn btn-sm btn-link">重置</a>
                @endif
            </form>
            <div style="font-size:12px; color:#8a97a8">
                领料单从工单详情页「生成领料单」产生，按 BOM × 计划数量 × 损耗率算料
            </div>
        </div>

        <table class="mes-table" style="font-size:13px">
            <thead>
            <tr>
                <th>领料单号</th>
                <th>工单 / 产品</th>
                <th class="num">行数</th>
                <th class="num">需求</th>
                <th class="num">已发</th>
                <th>发料进度</th>
                <th>状态</th>
                <th>发料时间</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($issues as $it)
                @php
                    $req = (float) $it->lines->sum('required_qty');
                    $got = (float) $it->lines->sum('issued_qty');
                    $rate = $req > 0 ? min(100, round($got / $req * 100)) : 0;
                @endphp
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">
                        <a href="{{ route('mes.issues.show', $it) }}" style="color:#16233f">{{ $it->issue_no }}</a>
                    </td>
                    <td>
                        <a href="{{ route('mes.work-orders.show', $it->work_order_id) }}" style="color:#16233f">
                            {{ $it->workOrder?->order_no }}
                        </a>
                        <div style="color:#8a97a8; font-size:12px">{{ $it->workOrder?->product?->name }}</div>
                    </td>
                    <td class="num">{{ $it->lines->count() }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($req, 3), '0'), '.') }}</td>
                    <td class="num" style="color:{{ $got > 0 ? '#227a39' : '#a8b3c1' }}">
                        {{ rtrim(rtrim(number_format($got, 3), '0'), '.') }}
                    </td>
                    <td style="min-width:120px">
                        <div style="height:6px; background:#e9eef4; border-radius:3px; overflow:hidden">
                            <div style="height:100%; width:{{ $rate }}%; background:{{ $rate >= 100 ? '#2f9e44' : ($rate > 0 ? '#f08c00' : '#c8d0da') }}"></div>
                        </div>
                        <div style="font-size:11px; color:#8a97a8; margin-top:3px">{{ $rate }}%</div>
                    </td>
                    <td>
                        <span class="mes-badge {{ $it->status === 'issued' ? 'b-done' : ($it->status === 'partial' ? 'b-progress' : 'b-draft') }}">
                            {{ ['draft' => '待发料', 'partial' => '部分发料', 'issued' => '已发齐'][$it->status] ?? $it->status }}
                        </span>
                    </td>
                    <td style="color:#6b7a90; font-size:12px">
                        {{ $it->issued_at?->format('m-d H:i') ?? '—' }}
                        @if ($it->issuer)
                            <div>by {{ $it->issuer->name }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">
                        <div class="mes-empty">
                            还没有领料单。到工单详情页点「生成领料单」，系统会按 BOM 把料算出来。
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>

        <div style="margin-top:14px">{{ $issues->links() }}</div>
    </div>
@endsection
