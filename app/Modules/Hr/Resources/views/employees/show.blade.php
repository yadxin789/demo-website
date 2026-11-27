@extends('hr::layouts.app')

@section('title', $employee->name . ' · 员工档案')
@section('sub', $employee->employee_no . ' · ' . ($employee->department?->name ?? '未分配部门'))

@section('content')
    <div class="hr-card mb-3">
        <div class="d-flex align-items-start gap-3 flex-wrap">
            <div class="hr-avatar">{{ mb_substr($employee->name, 0, 1) }}</div>
            <div class="flex-grow-1">
                <h2 style="font-size:20px; margin:0 0 6px">{{ $employee->name }}
                    <span class="hr-badge {{ $employee->status === 'active' ? 'b-done' : ($employee->status === 'probation' ? 'b-progress' : 'b-draft') }}">
                        {{ $statusText[$employee->status] ?? $employee->status }}
                    </span>
                </h2>
                <div style="color:#6f6f80; font-size:13px">
                    {{ $employee->employee_no }} ｜ {{ $employee->department?->name ?? '—' }} ｜ {{ $employee->position?->name ?? '—' }}
                    ｜ 入职 {{ $employee->hire_date?->format('Y-m-d') ?? '—' }}
                    @if ($employee->regular_date) ｜ 转正 {{ $employee->regular_date->format('Y-m-d') }} @endif
                    @if ($employee->leave_date) ｜ 离职 {{ $employee->leave_date->format('Y-m-d') }} @endif
                </div>
                <div style="color:#6f6f70; font-size:13px; margin-top:4px">
                    手机 {{ $employee->phone ?? '—' }} ｜ 邮箱 {{ $employee->email ?? '—' }} ｜ 学历 {{ $employee->education ?? '—' }}
                    ｜ 身份证 {{ $employee->id_card_masked ?? '—' }}
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.employees.edit', $employee->id) }}">改档案</a>
                @if ($employee->status === 'probation')
                    <form method="post" action="{{ route('hr.employees.regularize', $employee->id) }}" style="margin:0">
                        @csrf
                        <button class="btn btn-sm btn-primary" type="submit">办理转正</button>
                    </form>
                @endif
                @if ($employee->status !== 'left')
                    <form method="post" action="{{ route('hr.employees.leave', $employee->id) }}" style="margin:0"
                          onsubmit="return confirm('确认给 {{ $employee->name }} 办理离职？')">
                        @csrf
                        <input type="hidden" name="reason" value="演示环境手工办离职">
                        <button class="btn btn-sm btn-outline-danger" type="submit">办理离职</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="hr-card mb-3">
                <h3>合同（{{ $contracts->count() }} 份）</h3>
                <table class="hr-table">
                    <thead><tr><th>合同号</th><th>类型</th><th>起</th><th>止</th><th>状态</th></tr></thead>
                    <tbody>
                    @forelse ($contracts as $c)
                        <tr>
                            <td style="font-family:ui-monospace,Consolas,monospace">{{ $c->contract_no }}</td>
                            <td>{{ $c->type === 'open' ? '无固定期限' : '固定期限' }}</td>
                            <td>{{ $c->start_date?->format('Y-m-d') }}</td>
                            <td>{{ $c->end_date?->format('Y-m-d') ?? '—' }}</td>
                            <td>
                                @php($due = $c->end_date ? now()->startOfDay()->diffInDays($c->end_date, false) : null)
                                @if ($due !== null && $due <= 30)
                                    <span class="hr-badge b-bad">{{ $due }} 天到期</span>
                                @elseif ($due !== null && $due <= 90)
                                    <span class="hr-badge b-progress">{{ $due }} 天到期</span>
                                @else
                                    <span class="hr-badge b-done">正常</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="hr-empty">还没有合同记录。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="hr-card mb-3">
                <h3>异动流水（近 10 条）</h3>
                <table class="hr-table">
                    <thead><tr><th>生效日</th><th>类型</th><th>从</th><th>到</th><th>说明</th></tr></thead>
                    <tbody>
                    @forelse ($movements as $m)
                        <tr>
                            <td>{{ $m->effective_date?->format('Y-m-d') }}</td>
                            <td><span class="hr-badge b-draft">{{ $movementText[$m->type] ?? $m->type }}</span></td>
                            <td>{{ $m->fromDepartment?->name ?? '—' }}</td>
                            <td>{{ $m->toDepartment?->name ?? '—' }}</td>
                            <td style="color:#6f6f80">{{ $m->remark ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="hr-empty">还没有异动记录。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="hr-card mb-3">
                <h3>最近打卡（10 条）</h3>
                <table class="hr-table">
                    <thead><tr><th>日期</th><th>上班</th><th>下班</th><th>结果</th></tr></thead>
                    <tbody>
                    @forelse ($attendances as $a)
                        <tr>
                            <td>{{ $a->work_date?->format('m-d') ?? $a->work_date }}</td>
                            <td>{{ $a->clock_in ?? '—' }}</td>
                            <td>{{ $a->clock_out ?? '—' }}</td>
                            <td>
                                @php($map = ['normal' => ['正常', 'b-done'], 'late' => ['迟到', 'b-bad'], 'early' => ['早退', 'b-progress'], 'absent' => ['缺勤', 'b-bad'], 'missing' => ['缺卡', 'b-progress']])
                                @php($s = $map[$a->status] ?? [$a->status, 'b-draft'])
                                <span class="hr-badge {{ $s[1] }}">{{ $s[0] }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="hr-empty">暂无打卡记录。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="hr-card mb-3">
                <h3>工资条（近 6 期）</h3>
                <table class="hr-table">
                    <thead><tr><th>项目</th><th class="num">金额</th></tr></thead>
                    <tbody>
                    @forelse ($payrollItems as $p)
                        <tr>
                            <td>实发 · 第 {{ $loop->iteration }} 期</td>
                            <td class="num" style="font-weight:600">¥{{ number_format((float) $p->net_pay, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="hr-empty">暂无工资记录。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="hr-card">
                <h3>假期余额</h3>
                <table class="hr-table">
                    <thead><tr><th>年度</th><th>类型</th><th class="num">总额</th><th class="num">已用</th><th class="num">剩余</th></tr></thead>
                    <tbody>
                    @forelse ($balances as $b)
                        <tr>
                            <td>{{ $b->year }}</td>
                            <td>{{ ['annual' => '年假', 'sick' => '病假', 'personal' => '事假', 'compensatory' => '调休'][$b->type] ?? $b->type }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format((float) $b->total, 1), '0'), '.') }}</td>
                            <td class="num">{{ rtrim(rtrim(number_format((float) $b->used, 1), '0'), '.') }}</td>
                            <td class="num" style="font-weight:600">{{ rtrim(rtrim(number_format((float) $b->remaining, 1), '0'), '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="hr-empty">暂无余额记录。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
