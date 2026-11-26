@extends('mes::layouts.app')

@section('title', '车间工作台')
@section('sub', '精工精密制造（苏州）· 一号车间 · 今日')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi orange">
            <div class="lb">在制工单</div>
            <div class="vl">{{ $inProgress }}</div>
            <div class="ft">已下达 + 生产中</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lb">今日良品</div>
            <div class="vl">{{ rtrim(rtrim(number_format($todayOk, 0, '.', ','), ','), '.') }}</div>
            <div class="ft">件</div>
        </div>
        <div class="mes-kpi">
            <div class="lb">今日良率</div>
            <div class="vl">{{ $yield }}%</div>
            <div class="ft">良品 /（良品 + 不良）</div>
        </div>
        <div class="mes-kpi bad">
            <div class="lb">待处理不良</div>
            <div class="vl">{{ $openDefects }}</div>
            <div class="ft">open 状态</div>
        </div>
        <div class="mes-kpi">
            <div class="lb">设备异常</div>
            <div class="vl">{{ $equipIssues }}</div>
            <div class="ft">维修中 / 报废</div>
        </div>
    </div>

    <div class="mes-card" style="margin-top:16px">
        <h3>工单进度</h3>
        @if ($orders->isEmpty())
            <div class="mes-empty">暂无工单。执行 <code>php artisan db:seed --class="App\Modules\Mes\Database\Seeders\DatabaseSeeder" --database=mes</code> 灌入演示数据。</div>
        @else
            <table class="mes-table">
                <thead>
                <tr>
                    <th>工单号</th>
                    <th>成品</th>
                    <th class="num">计划</th>
                    <th class="num">完成</th>
                    <th style="width:160px">进度</th>
                    <th>状态</th>
                    <th>交期</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($orders as $o)
                    <tr>
                        <td><a href="{{ route('mes.work-orders.show', $o) }}">{{ $o->order_no }}</a></td>
                        <td>{{ $o->product?->name }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $o->plan_qty, 0, '.', ','), ','), '.') }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $o->done_qty, 0, '.', ','), ','), '.') }}</td>
                        <td>
                            <div class="mes-progress"><span style="width: {{ min(100, $o->progress) }}%"></span></div>
                            <span style="font-size:12px;color:#6b7a90">{{ $o->progress }}%</span>
                        </td>
                        <td>
                            @php
                                $cls = match ($o->status) {
                                    'draft' => 'b-draft',
                                    'released' => 'b-released',
                                    'in_progress' => 'b-progress',
                                    'completed', 'closed' => 'b-done',
                                    default => 'b-draft',
                                };
                                $label = match ($o->status) {
                                    'draft' => '草稿',
                                    'released' => '已下达',
                                    'in_progress' => '生产中',
                                    'completed' => '已完工',
                                    'closed' => '已关闭',
                                    default => $o->status,
                                };
                            @endphp
                            <span class="mes-badge {{ $cls }}">{{ $label }}</span>
                        </td>
                        <td>{{ $o->plan_end?->format('m-d') ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="mes-card" style="margin-top:16px">
        <h3>演示走查建议（5 分钟）</h3>
        <ol style="margin:0; padding-left:18px; line-height:2; font-size:13.5px; color:#42526b">
            <li>先看「工单」列表：在制、待下达、卡质检的工单各有一张，进度条能看出差异。</li>
            <li>点开一张工单的详情：工序进度、报工流水、领料、检验四个 Tab。</li>
            <li>去「车间看板」：工作中心 × 5 天的排产甘特图。</li>
            <li>回「主数据」看物料与 BOM：编码统一 <b>MJ-</b> 前缀，与 ERP 的 <b>MAT-</b> 明确区分。</li>
            <li>需要把您现有系统里的物料搬过来？用「Excel 导入」，模板可直接下载。</li>
        </ol>
    </div>
@endsection
