@extends('mes::layouts.app')

@section('title', '我的任务')
@section('sub', $isAdmin ? '管理员视角：全车间在制工序' : '派给我的工序，报工后自动推进')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">待开工</div>
            <div class="num">{{ $stat['waiting'] }}</div>
            <div class="hint">已派工还没动</div>
        </div>
        <div class="mes-kpi orange">
            <div class="lbl">进行中</div>
            <div class="num">{{ $stat['running'] }}</div>
            <div class="hint">正在做的工序</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">我今日报工</div>
            <div class="num">{{ $stat['today_count'] }}</div>
            <div class="hint">笔数</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">我今日合格</div>
            <div class="num">{{ rtrim(rtrim(number_format($stat['today_ok'], 3), '0'), '.') }}</div>
            <div class="hint">件</div>
        </div>
    </div>

    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:14px">
            <div style="font-size:13px; color:#4a5568">
                @if ($isAdmin && ! $onlyMine)
                    <b>{{ $me->name }}</b> 是管理员，下面列的是<b>全车间</b>待做工序；
                @else
                    <b>{{ $me->name }}</b> 名下待做工序；
                @endif
                报工后工序完成数自动累加，做满计划量这道工序就转「已完成」。
            </div>
            @if ($isAdmin)
                <div style="display:flex; gap:8px">
                    <a class="btn btn-sm {{ $onlyMine ? 'btn-outline-secondary' : 'btn-primary' }}" href="{{ route('mes.my-tasks.index') }}">全车间</a>
                    <a class="btn btn-sm {{ $onlyMine ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('mes.my-tasks.index', ['mine' => 1]) }}">只看我的</a>
                </div>
            @endif
        </div>

        @forelse ($ops as $op)
            @php
                $done = (float) $op->done_qty;
                $plan = (float) $op->plan_qty;
                $rate = $plan > 0 ? min(100, (int) round($done / $plan * 100)) : 0;
                $remain = max(0, $plan - $done);
            @endphp
            <div style="border:1px solid #e3e9f2; border-radius:10px; padding:14px; margin-bottom:12px; background:#fff">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px; flex-wrap:wrap">
                    <div>
                        <div style="font-family:ui-monospace,Consolas,monospace; font-size:13px; color:#6b7a90">
                            {{ $op->workOrder?->order_no }} · 优先级 P{{ $op->workOrder?->priority }}
                        </div>
                        <div style="font-size:16px; font-weight:600; color:#16233f; margin-top:2px">
                           {{ $op->op_no }} {{ $op->op_name }}
                        </div>
                        <div style="font-size:13px; color:#6b7a90; margin-top:2px">
                            {{ $op->workOrder?->product?->name }} · {{ $op->workCenter?->name ?? '未指定工作中心' }}
                        </div>
                    </div>
                    <div style="text-align:right">
                        <span class="mes-badge {{ $op->status === 'in_progress' ? 'b-progress' : 'b-released' }}">
                            {{ $op->status === 'in_progress' ? '进行中' : '待开工' }}
                        </span>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:12px; margin-top:10px; flex-wrap:wrap">
                    <div style="flex:1; min-width:180px">
                        <div style="height:8px; background:#e9eef4; border-radius:4px; overflow:hidden">
                            <div style="height:100%; width:{{ $rate }}%; background:{{ $rate >= 100 ? '#2f9e44' : '#f08c00' }}"></div>
                        </div>
                        <div style="font-size:12px; color:#6b7a90; margin-top:4px">
                            已做 {{ rtrim(rtrim(number_format($done, 3), '0'), '.') }} / {{ rtrim(rtrim(number_format($plan, 3), '0'), '.') }}
                            · 还剩 <b style="color:#a25c00">{{ rtrim(rtrim(number_format($remain, 3), '0'), '.') }}</b>
                        </div>
                    </div>
                    <a class="btn btn-primary btn-lg" style="min-width:150px" href="{{ route('mes.my-tasks.report', $op) }}">去报工</a>
                </div>
            </div>
        @empty
            <div class="mes-empty">
                @if ($isAdmin && ! $onlyMine)
                    全车间没有待做工序 —— 工单都完成了，或者还没下达。
                @else
                    你名下暂时没有派到工序。可以让班组长在工单详情页「一键派工」，或切到「全车间」看别人的活。
                @endif
            </div>
        @endforelse

        <div style="margin-top:6px">{{ $ops->links() }}</div>
    </div>
@endsection
