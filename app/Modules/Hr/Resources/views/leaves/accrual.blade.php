@extends('hr::layouts.app')

@section('title', '年假核算')
@section('sub', '按司龄规则算应享年假，先看差异再一键发放')

@section('content')
    <div class="hr-kpis">
        <div class="hr-kpi">
            <div class="lbl">{{ $year }} 年在职人数</div>
            <div class="num">{{ $stat['people'] }}<small>人</small></div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">应授予合计</div>
            <div class="num">{{ number_format($stat['entitled_days'], 1) }}<small>天</small></div>
        </div>
        <div class="hr-kpi warn">
            <div class="lbl">需调整额度</div>
            <div class="num">{{ $stat['need_adjust'] }}<small>人</small></div>
            <div class="hint">现额度与规则不一致</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">尚无年假记录</div>
            <div class="num">{{ $stat['no_balance'] }}<small>人</small></div>
            <div class="hint">发放时会自动补建</div>
        </div>
    </div>

    <div class="hr-card">
        <h3 style="margin:0 0 10px; font-size:14px">核算规则（可在此页面对照，改规则需改代码）</h3>
        <table class="hr-table">
            <thead><tr><th>司龄段</th><th class="num">年假天数</th></tr></thead>
            <tbody>
            @foreach ($rules as $r)
                <tr>
                    <td>{{ $r['label'] }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($r['days'], 1), '0'), '.') }} 天</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="mini" style="margin-top:8px">
            发放只覆盖 <strong>年假（annual）</strong> 的 total / remaining，已休（used）不动；离职员工不参与核算。
        </div>
    </div>

    <div class="hr-card">
        <form method="post" action="{{ route('hr.leaves.accrual.run') }}" style="display:flex; gap:10px; align-items:center; margin-bottom:12px"
              onsubmit="return confirm('确认按上述规则发放 {{ $year }} 年年假额度？已休天数不会被清除。')">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">
            <label class="mini">核算年度</label>
            <select class="form-select form-select-sm" style="width:120px" onchange="location.href='{{ route('hr.leaves.accrual') }}?year='+this.value">
                @for ($y = (int) now()->format('Y') - 1; $y <= (int) now()->format('Y') + 1; $y++)
                    <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
                @endfor
            </select>
            <button class="btn btn-sm btn-primary">一键发放 {{ $year }} 年年假</button>
            <a class="btn btn-sm btn-light" href="{{ route('hr.leaves.balances', ['year' => $year, 'type' => 'annual']) }}">去看假期余额</a>
        </form>

        <table class="hr-table">
            <thead>
            <tr>
                <th>工号</th>
                <th>姓名</th>
                <th>部门</th>
                <th>入职日期</th>
                <th class="num">司龄</th>
                <th>适用档</th>
                <th class="num">应享</th>
                <th class="num">现额度</th>
                <th class="num">已休</th>
                <th class="num">差额</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['employee']->employee_no }}</td>
                    <td>{{ $r['employee']->name }}</td>
                    <td>{{ $r['employee']->department?->name ?? '—' }}</td>
                    <td>{{ $r['hire_date'] ?? '—' }}</td>
                    <td class="num">{{ number_format($r['years'], 1) }} 年</td>
                    <td><span class="mini">{{ $r['label'] }}</span></td>
                    <td class="num">{{ rtrim(rtrim(number_format($r['entitled'], 1), '0'), '.') }}</td>
                    <td class="num">
                        @if ($r['has_balance'])
                            {{ rtrim(rtrim(number_format($r['current_total'], 1), '0'), '.') }}
                        @else
                            <span class="mini">未建</span>
                        @endif
                    </td>
                    <td class="num">{{ rtrim(rtrim(number_format($r['used'], 1), '0'), '.') }}</td>
                    <td class="num">
                        @if (abs($r['delta']) < 0.01)
                            0
                        @elseif ($r['delta'] > 0)
                            <span style="color:#0a7c42">+{{ rtrim(rtrim(number_format($r['delta'], 1), '0'), '.') }}</span>
                        @else
                            <span style="color:#c0392b">{{ rtrim(rtrim(number_format($r['delta'], 1), '0'), '.') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="mini">没有在职员工。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
