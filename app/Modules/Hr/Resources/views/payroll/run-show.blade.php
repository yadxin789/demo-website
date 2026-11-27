@extends('hr::layouts.app')

@section('title', $run->payroll_no . ' · 工资明细')
@section('sub', '' . $run->period . ' 期间，' . ($statusText[$run->status] ?? $run->status))

@section('content')
    <div class="hr-card mb-3">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
                <h3 style="margin:0 0 6px">{{ $run->period }} 工资单
                    @php($map = ['draft' => 'b-draft', 'confirmed' => 'b-progress', 'paid' => 'b-done'])
                    <span class="hr-badge {{ $map[$run->status] ?? 'b-draft' }}">{{ $statusText[$run->status] ?? $run->status }}</span>
                </h3>
                <div style="color:#6f6f80; font-size:13px">
                    单号 {{ $run->payroll_no }} ｜ 人数 {{ $summary['count'] }} ｜ 人均实发 ¥{{ number_format($summary['avg_net'], 2) }}
                    ｜ 生成 {{ $run->generated_at?->format('Y-m-d H:i') ?? '—' }}
                    @if ($run->paid_at) ｜ 发放 {{ $run->paid_at->format('Y-m-d H:i') }} @endif
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.payroll.runs.export', $run->id) }}">导出 CSV</a>
                @if ($run->status === 'draft')
                    <form method="post" action="{{ route('hr.payroll.runs.confirm', $run->id) }}" style="margin:0">
                        @csrf
                        <button class="btn btn-sm btn-primary" type="submit">确认工资单</button>
                    </form>
                @endif
                @if ($run->status === 'confirmed')
                    <form method="post" action="{{ route('hr.payroll.runs.pay', $run->id) }}" style="margin:0"
                          onsubmit="return confirm('确认已按这张工资单实际发放？')">
                        @csrf
                        <button class="btn btn-sm btn-primary" type="submit">标记已发放</button>
                    </form>
                @endif
                <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.payroll.runs.index') }}">返回批次</a>
            </div>
        </div>
    </div>

    <div class="hr-kpis mb-3">
        <div class="hr-kpi purple">
            <div class="lbl">应发合计</div>
            <div class="num" style="font-size:18px">¥{{ number_format($summary['gross'], 2) }}</div>
            <div class="hint">四段 + 加班费</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">实发合计</div>
            <div class="num" style="font-size:18px">¥{{ number_format($summary['net'], 2) }}</div>
            <div class="hint">扣完社保个税</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">请假扣款</div>
            <div class="num" style="font-size:18px">¥{{ number_format($summary['deduct'], 2) }}</div>
            <div class="hint">按 21.75 天折算</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">社保 + 个税</div>
            <div class="num" style="font-size:18px">¥{{ number_format($summary['social'] + $summary['tax'], 2) }}</div>
            <div class="hint">社保 ¥{{ number_format($summary['social'], 2) }} / 个税 ¥{{ number_format($summary['tax'], 2) }}</div>
        </div>
    </div>

    <div class="hr-card">
        <h3>逐人明细 {{ $rows->total() }} 条</h3>
        <table class="hr-table">
            <thead>
            <tr>
                <th>工号</th><th>姓名</th><th>部门</th>
                <th class="num">基本</th><th class="num">岗位</th><th class="num">绩效</th><th class="num">补贴</th>
                <th class="num">加班费</th><th class="num">应发</th>
                <th class="num">请假扣</th><th class="num">社保</th><th class="num">个税</th><th class="num">实发</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $i)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $i->employee_no }}</td>
                    <td>{{ $i->employee_name }}</td>
                    <td>{{ $i->employee?->department?->name ?? '—' }}</td>
                    <td class="num">{{ number_format((float) $i->base, 0) }}</td>
                    <td class="num">{{ number_format((float) $i->post, 0) }}</td>
                    <td class="num">{{ number_format((float) $i->performance, 0) }}</td>
                    <td class="num">{{ number_format((float) $i->allowance, 0) }}</td>
                    <td class="num">{{ number_format((float) $i->overtime_pay, 0) }}</td>
                    <td class="num" style="font-weight:600">{{ number_format((float) $i->gross, 2) }}</td>
                    <td class="num" style="color:#d6336c">{{ number_format((float) $i->leave_deduct, 2) }}</td>
                    <td class="num">{{ number_format((float) $i->social_insurance, 2) }}</td>
                    <td class="num">{{ number_format((float) $i->tax, 2) }}</td>
                    <td class="num" style="font-weight:700; color:#5f3dc4">{{ number_format((float) $i->net_pay, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="13" class="hr-empty">这张工资单还没有明细。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>
@endsection
