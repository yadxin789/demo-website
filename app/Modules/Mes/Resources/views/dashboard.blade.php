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

    @if ($pendingInspections->isNotEmpty())
        <div class="mes-card" style="margin-top:16px; border-left:4px solid #f08c00">
            <h3 style="display:flex; justify-content:space-between; align-items:center">
                <span>质检待办 · {{ $pendingInspections->count() }} 张</span>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.quality.inspections.index', ['result' => 'pending']) }}">全部待检</a>
            </h3>
            <table class="mes-table">
                <thead>
                <tr><th>检验单号</th><th>工单</th><th>模板</th><th>类型</th><th style="width:120px">操作</th></tr>
                </thead>
                <tbody>
                @foreach ($pendingInspections as $ins)
                    <tr>
                        <td style="font-family:ui-monospace,Consolas,monospace">{{ $ins->inspection_no }}</td>
                        <td>
                            {{ $ins->workOrder?->order_no }}
                            <span style="color:#8a97a8; font-size:12px">{{ $ins->workOrder?->product?->name }}</span>
                        </td>
                        <td style="color:#4a5568">{{ $ins->template?->name }}</td>
                        <td><span class="mes-badge {{ $ins->type === 'IQC' ? 'b-draft' : ($ins->type === 'IPQC' ? 'b-progress' : 'b-released') }}">{{ $ins->type }}</span></td>
                        <td><a class="btn btn-sm btn-primary" href="{{ route('mes.quality.inspections.show', $ins) }}">去判定</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

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
            <li>「工单」列表：在制、待下达、卡质检的工单各有一张，进度条能看出差异。</li>
            <li>点开一张工单：工序时间轴、齐套欠料检查、最近报工、检验结果、领料单一屏看全。</li>
            <li>「我的任务」切到 <b>只看我的</b>，用手机尺寸打开报工页 —— 操作工扫码就能报工。</li>
            <li>「质检待办」里挑一张去判定，填错一项就是不合格，接着去「不良记录」转返工。</li>
            <li>需要把您现有系统里的物料搬过来？用「Excel 导入」，模板可直接下载。</li>
        </ol>
    </div>
@endsection
