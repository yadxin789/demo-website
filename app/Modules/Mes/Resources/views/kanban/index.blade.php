@php
    /** 车间大屏：1920×1080 一屏不滚动，30 秒自动刷新（M6-02） */
@endphp
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">
    <title>车间看板 · 精工精密制造</title>
    <style>
        :root {
            --bg: #08111f;
            --panel: #0e1b2e;
            --panel2: #12233a;
            --line: rgba(255, 255, 255, .09);
            --txt: #e8eef8;
            --dim: #8fa3bd;
            --cyan: #35c2f0;
            --orange: #ff8a2b;
            --green: #37d67a;
            --red: #ff5b5b;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body {
            background:
                radial-gradient(1200px 600px at 15% -10%, rgba(53, 194, 240, .16), transparent 60%),
                radial-gradient(900px 500px at 95% 0%, rgba(255, 138, 43, .12), transparent 55%),
                var(--bg);
            color: var(--txt);
            font-family: "PingFang SC", "Microsoft YaHei", system-ui, sans-serif;
            overflow: hidden;
        }
        .kb { display: flex; flex-direction: column; height: 100vh; padding: 18px 26px 14px; gap: 14px; }
        .kb-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; }
        .kb-head .brand { display: flex; align-items: center; gap: 14px; }
        .kb-head .mark {
            width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center;
            background: linear-gradient(135deg, var(--cyan), #1677b8); color: #04121f;
            font-weight: 800; font-size: 17px; letter-spacing: .5px;
        }
        .kb-head h1 { margin: 0; font-size: 25px; letter-spacing: 1px; }
        .kb-head .sub { margin-top: 3px; font-size: 13px; color: var(--dim); letter-spacing: .4px; }
        .kb-head .clock { text-align: right; }
        .kb-head .clock b { font-size: 32px; font-variant-numeric: tabular-nums; letter-spacing: 1px; }
        .kb-head .clock span { display: block; font-size: 12px; color: var(--dim); margin-top: 2px; }

        .kb-kpis { display: grid; grid-template-columns: repeat(6, 1fr); gap: 12px; }
        .kpi {
            background: linear-gradient(180deg, var(--panel2), var(--panel));
            border: 1px solid var(--line); border-radius: 14px; padding: 12px 16px 14px;
            position: relative; overflow: hidden;
        }
        .kpi::after { content: ""; position: absolute; left: 0; top: 0; width: 4px; height: 100%; background: var(--cyan); opacity: .85; }
        .kpi.k-green::after { background: var(--green); }
        .kpi.k-orange::after { background: var(--orange); }
        .kpi.k-red::after { background: var(--red); }
        .kpi .lbl { font-size: 13px; color: var(--dim); letter-spacing: .6px; }
        .kpi .num { font-size: 42px; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.15; margin-top: 2px; }
        .kpi .num small { font-size: 15px; font-weight: 500; color: var(--dim); margin-left: 4px; }
        .kpi .ft { font-size: 12px; color: var(--dim); margin-top: 2px; }

        .kb-body { flex: 1; display: grid; grid-template-columns: 1.25fr 1fr; gap: 12px; min-height: 0; }
        .panel {
            background: linear-gradient(180deg, var(--panel2), var(--panel));
            border: 1px solid var(--line); border-radius: 14px; padding: 14px 18px 12px;
            display: flex; flex-direction: column; min-height: 0;
        }
        .panel h2 { margin: 0 0 10px; font-size: 16px; letter-spacing: .8px; display: flex; align-items: center; gap: 8px; }
        .panel h2 i { width: 6px; height: 6px; border-radius: 50%; background: var(--cyan); display: inline-block; }

        .center-row { padding: 10px 0 12px; border-bottom: 1px solid var(--line); }
        .center-row:last-child { border-bottom: 0; }
        .center-row .top { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
        .center-row .name { font-size: 17px; font-weight: 600; }
        .center-row .name span { font-size: 12px; color: var(--dim); font-weight: 400; margin-left: 8px; }
        .center-row .qty { font-size: 15px; color: var(--dim); font-variant-numeric: tabular-nums; }
        .center-row .qty b { color: var(--txt); font-size: 20px; }
        .bar { height: 12px; border-radius: 8px; background: rgba(255, 255, 255, .07); margin-top: 8px; overflow: hidden; }
        .bar > div { height: 100%; border-radius: 8px; background: linear-gradient(90deg, var(--cyan), #57e0a5); }
        .center-row .meta { margin-top: 6px; font-size: 12px; color: var(--dim); display: flex; gap: 16px; }
        .center-row .meta b { color: var(--orange); font-weight: 600; }

        .kb-right { display: grid; grid-template-rows: 1.05fr 1fr; gap: 12px; min-height: 0; }
        table.feed { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        table.feed th { text-align: left; color: var(--dim); font-weight: 500; font-size: 12px; padding: 4px 6px; border-bottom: 1px solid var(--line); }
        table.feed td { padding: 6px; border-bottom: 1px solid rgba(255, 255, 255, .05); font-variant-numeric: tabular-nums; }
        table.feed td.ok { color: var(--green); text-align: right; }
        table.feed td.ng { color: var(--red); text-align: right; }
        table.feed td.mono { color: var(--dim); font-family: ui-monospace, Consolas, monospace; font-size: 12px; }

        .alerts { display: flex; flex-direction: column; gap: 8px; overflow: hidden; }
        .alert {
            display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px;
            background: rgba(255, 255, 255, .04); border: 1px solid var(--line); font-size: 14px;
        }
        .alert .tag {
            flex: 0 0 auto; font-size: 12px; padding: 2px 8px; border-radius: 999px;
            background: rgba(255, 138, 43, .16); color: var(--orange); border: 1px solid rgba(255, 138, 43, .3);
        }
        .alert.bad .tag { background: rgba(255, 91, 91, .16); color: var(--red); border-color: rgba(255, 91, 91, .32); }
        .alert .txt { color: #cddaea; }
        .alert.none { color: var(--green); }
        .alert.none .tag { background: rgba(55, 214, 122, .16); color: var(--green); border-color: rgba(55, 214, 122, .3); }

        .kb-foot { display: flex; justify-content: space-between; font-size: 12px; color: var(--dim); letter-spacing: .3px; }
        .kb-foot a { color: var(--cyan); text-decoration: none; }
    </style>
</head>
<body>
<div class="kb">
    <div class="kb-head">
        <div class="brand">
            <div class="mark">MES</div>
            <div>
                <h1>精工精密制造 · 一车间生产看板</h1>
                <div class="sub">数据源：工单 / 报工 / 检验 / 设备 · 良率口径 ok ÷ (ok + 不良) · 工单完成数取瓶颈工序</div>
            </div>
        </div>
        <div class="clock">
            <b>{{ $k['generated_at']->format('H:i:s') }}</b>
            <span>{{ $k['generated_at']->format('Y-m-d') }} · 每 30 秒自动刷新</span>
        </div>
    </div>

    <div class="kb-kpis">
        <div class="kpi k-green">
            <div class="lbl">今日产出</div>
            <div class="num">{{ number_format($k['ok_today'], 0) }}<small>件</small></div>
            <div class="ft">合格品 · 今日报工累计</div>
        </div>
        <div class="kpi {{ $k['yield_today'] >= 97 ? 'k-green' : ($k['yield_today'] >= 93 ? 'k-orange' : 'k-red') }}">
            <div class="lbl">良率</div>
            <div class="num">{{ number_format($k['yield_today'], 1) }}<small>%</small></div>
            <div class="ft">{{ $k['yield_source'] === 'today' ? '今日口径' : '今日无报工，取近 7 天' }}</div>
        </div>
        <div class="kpi k-orange">
            <div class="lbl">在制工单</div>
            <div class="num">{{ $k['wip_orders'] }}<small>张</small></div>
            <div class="ft">已下达 + 生产中</div>
        </div>
        <div class="kpi">
            <div class="lbl">在制待完成</div>
            <div class="num">{{ number_format((int) $k['wip_remain'], 0) }}<small>件</small></div>
            <div class="ft">计划 {{ number_format((int) $k['wip_qty'], 0) }} 件 · 剩 {{ $k['wip_qty'] > 0 ? round($k['wip_remain'] / $k['wip_qty'] * 100) : 0 }}%</div>
        </div>
        <div class="kpi {{ $k['equip_running'] >= $k['equip_total'] ? 'k-green' : 'k-orange' }}">
            <div class="lbl">设备运行</div>
            <div class="num">{{ $k['equip_running'] }}<small>/ {{ $k['equip_total'] }} 台</small></div>
            <div class="ft">非运行 {{ $k['equip_total'] - $k['equip_running'] }} 台 · 含空闲 / 维修中 / 报废</div>
        </div>
        <div class="kpi">
            <div class="lbl">今日报工</div>
            <div class="num">{{ $k['reports_today'] }}<small>笔</small></div>
            <div class="ft">工时 {{ number_format($k['minutes_today']) }} 分钟 · 不良 {{ number_format($k['ng_today'], 0) }} 件</div>
        </div>
    </div>

    <div class="kb-body">
        <section class="panel">
            <h2><i></i>工作中心在制负荷</h2>
            @foreach ($k['centers'] as $c)
                <div class="center-row">
                    <div class="top">
                        <div class="name">{{ $c['name'] }}<span>{{ $c['code'] }}</span></div>
                        <div class="qty"><b>{{ number_format((int) $c['qty_done'], 0) }}</b> / {{ number_format((int) $c['qty_plan'], 0) }} 件</div>
                    </div>
                    <div class="bar"><div style="width: {{ $c['pct'] }}%"></div></div>
                    <div class="meta">
                        <span>在制工序 <b>{{ $c['active'] }}</b></span>
                        <span>已完成工序 {{ $c['done_ops'] }}</span>
                        <span>共 {{ $c['ops'] }} 道 · 完成率 {{ $c['pct'] }}%</span>
                    </div>
                </div>
            @endforeach
        </section>

        <div class="kb-right">
            <section class="panel">
                <h2><i></i>最近报工流水</h2>
                <table class="feed">
                    <thead><tr><th>时间</th><th>工单</th><th>工序</th><th>报工人</th><th style="text-align:right">合格</th><th style="text-align:right">不良</th></tr></thead>
                    <tbody>
                    @forelse ($k['feed'] as $f)
                        <tr>
                            <td class="mono">{{ $f['time'] }}</td>
                            <td class="mono">{{ $f['order'] }}</td>
                            <td>{{ $f['op'] }}</td>
                            <td style="color:var(--dim)">{{ $f['user'] }}</td>
                            <td class="ok">{{ number_format($f['ok'], 0) }}</td>
                            <td class="ng">{{ $f['ng'] > 0 ? number_format($f['ng'], 0) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:var(--dim); padding:14px 6px">今天还没有报工记录。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </section>

            <section class="panel">
                <h2><i style="background:var(--orange)"></i>异常提醒</h2>
                <div class="alerts">
                    @forelse ($k['alerts'] as $a)
                        <div class="alert {{ $a['tone'] === 'bad' ? 'bad' : '' }}">
                            <span class="tag">{{ $a['type'] }}</span>
                            <span class="txt">{{ $a['text'] }}</span>
                        </div>
                    @empty
                        <div class="alert none">
                            <span class="tag">正常</span>
                            <span class="txt">无缺料、无待检、无停机、无逾期工单。</span>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <div class="kb-foot">
        <span>精工精密制造（苏州）· 一号车间 · MES 演示环境</span>
        <span><a href="{{ route('mes.dashboard') }}">← 返回工作台</a></span>
    </div>
</div>
</body>
</html>
