@extends('hr::layouts.app')

@section('title', '人数结构')
@section('sub', '华瑞集团（控股）· 在册口径 = 在职 + 试用，离职单列')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi purple">
            <div class="lbl">在册</div>
            <div class="num">{{ $kpi['active'] }}</div>
            <div class="hint">在职 {{ $kpi['active'] - $kpi['probation'] }} + 试用 {{ $kpi['probation'] }}</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">部门</div>
            <div class="num">{{ $kpi['depts'] }}</div>
            <div class="hint">三级组织</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">平均司龄</div>
            <div class="num">{{ $kpi['avg_tenure'] }}</div>
            <div class="hint">单位：年</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">离职</div>
            <div class="num">{{ $kpi['left'] }}</div>
            <div class="hint">累计离职人数</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="hr-card">
                <h3>部门人数与编制</h3>
                @php($maxN = max(1, max(array_column($rows, 'n'))))
                <table class="hr-table">
                    <thead><tr><th>部门</th><th>编码</th><th class="num">在册</th><th>占比</th><th class="num">编制</th><th class="num">缺口</th></tr></thead>
                    <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>{{ $r['name'] }}</td>
                            <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $r['code'] }}</td>
                            <td class="num">{{ $r['n'] }}</td>
                            <td style="min-width:110px">
                                <div class="hr-progress"><span style="width: {{ (int) round($r['n'] / $maxN * 100) }}%"></span></div>
                            </td>
                            <td class="num">{{ $r['planned'] }}</td>
                            <td class="num" style="color:{{ $r['planned'] - $r['n'] > 0 ? '#d6336c' : '#0ca678' }}">
                                {{ $r['planned'] - $r['n'] }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="hr-card">
                <h3>人员状态</h3>
                <table class="hr-table">
                    <thead><tr><th>状态</th><th class="num">人数</th></tr></thead>
                    <tbody>
                    @foreach ($statusText as $k => $v)
                        <tr><td>{{ $v }}</td><td class="num">{{ (int) ($byStatus[$k] ?? 0) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="hr-card mt-3">
                <h3>司龄分布</h3>
                <table class="hr-table">
                    <thead><tr><th>区间</th><th class="num">人数</th></tr></thead>
                    <tbody>
                    @foreach ($tenure as $k => $n)
                        <tr><td>{{ $k }}</td><td class="num">{{ $n }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="hr-card mt-3">
                <h3>学历分布</h3>
                <table class="hr-table">
                    <thead><tr><th>学历</th><th class="num">人数</th></tr></thead>
                    <tbody>
                    @forelse ($byEducation as $edu => $n)
                        <tr><td>{{ $edu ?: '未填' }}</td><td class="num">{{ $n }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="hr-empty">没有学历数据。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="hr-card mt-3">
                <h3>岗位职级</h3>
                <table class="hr-table">
                    <thead><tr><th>职级</th><th class="num">岗位数</th></tr></thead>
                    <tbody>
                    @foreach ($gradeRows as $g)
                        <tr><td>{{ $g->grade ?: '未定级' }}</td><td class="num">{{ $g->n }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
