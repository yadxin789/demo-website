@extends('hr::layouts.app')

@section('title', '我的自助')
@section('sub', '考勤、假期、排班、培训、证书与工资条，自己看不用找 HR')

@section('content')
    @if (! $employee)
        <div class="hr-card">
            <h3 style="margin:0 0 8px">这个账号还没绑定员工档案</h3>
            <div style="color:#6f6f80;font-size:13px">
                当前账号 {{ $user?->name }} 的员工编号是「{{ $user?->employee_no ?: '未设置' }}」，
                在员工档案里找不到对应记录。请让 HR 在「员工」里补一个同编号的档案，或把账号的 employee_no 改成已有档案的编号。
            </div>
        </div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-3">
                <div class="hr-card">
                    <div style="color:#6f6f80;font-size:12px">本月出勤</div>
                    <div style="font-size:24px;font-weight:700">{{ (int) ($monthStats->days ?? 0) }}<small style="font-size:12px"> 天</small></div>
                    <div style="font-size:12px;color:#8a97a8">迟到 {{ (int) ($monthStats->late ?? 0) }} 分 · 早退 {{ (int) ($monthStats->early ?? 0) }} 分</div>
                </div>
            </div>
            <div class="col-3">
                <div class="hr-card">
                    <div style="color:#6f6f80;font-size:12px">假期余额</div>
                    @php($mainBal = $balances->first())
                    <div style="font-size:24px;font-weight:700">{{ $mainBal ? rtrim(rtrim(number_format((float) $mainBal->remaining, 2), '0'), '.') : '—' }}<small style="font-size:12px"> 天</small></div>
                    <div style="font-size:12px;color:#8a97a8">{{ $mainBal?->type ?? '暂无额度' }} · {{ $mainBal?->year ?? '' }}</div>
                </div>
            </div>
            <div class="col-3">
                <div class="hr-card">
                    <div style="color:#6f6f80;font-size:12px">最新工资（实发）</div>
                    <div style="font-size:24px;font-weight:700">{{ $latestPayroll ? number_format((float) $latestPayroll->net_pay, 0) : '—' }}<small style="font-size:12px"> 元</small></div>
                    <div style="font-size:12px;color:#8a97a8"><a href="{{ route('hr.payroll.my') }}">看工资条明细</a></div>
                </div>
            </div>
            <div class="col-3">
                <div class="hr-card">
                    <div style="color:#6f6f80;font-size:12px">合同</div>
                    <div style="font-size:16px;font-weight:700">{{ $contract?->contract_no ?? '未签' }}</div>
                    <div style="font-size:12px;color:{{ $contract?->end_date && $contract->end_date->lt(now()->addDays(60)) ? '#c92a2a' : '#8a97a8' }}">
                        {{ $contract?->end_date ? '至 ' . $contract->end_date->toDateString() : '' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="hr-card mb-3">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div style="font-size:18px;font-weight:700">{{ $employee->name }}</div>
                <span class="hr-badge b-done">{{ $employee->employee_no }}</span>
                <span style="color:#6f6f80;font-size:13px">{{ $employee->department?->name }} · {{ $employee->position?->name }}</span>
                <span style="color:#6f6f80;font-size:13px">入职 {{ $employee->hire_date?->toDateString() }}</span>
                <span class="hr-badge {{ $employee->status === 'active' ? 'b-done' : 'b-draft' }}">{{ $employee->status }}</span>
                <a class="btn btn-sm btn-outline-secondary ms-auto" href="{{ route('hr.employees.show', $employee) }}">看我的档案</a>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-6">
                <div class="hr-card">
                    <h3 style="margin:0 0 10px">本周排班</h3>
                    <table class="hr-table">
                        <thead><tr><th>日期</th><th>班次</th><th>时间</th><th>状态</th></tr></thead>
                        <tbody>
                        @forelse ($roster as $r)
                            <tr>
                                <td>{{ $r->work_date->toDateString() }}（{{ ['日','一','二','三','四','五','六'][$r->work_date->dayOfWeek] }}）</td>
                                <td>{{ $r->shift?->code }} {{ $r->shift?->name }}</td>
                                <td>{{ $r->shift?->work_start }}-{{ $r->shift?->work_end }}</td>
                                <td><span class="hr-badge {{ $r->status === 'planned' ? 'b-draft' : ($r->status === 'leave' ? 'b-bad' : 'b-done') }}">{{ $r->status }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="color:#8a97a8">本周还没排班。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-6">
                <div class="hr-card">
                    <h3 style="margin:0 0 10px">本月考勤（最近 10 条）</h3>
                    <table class="hr-table">
                        <thead><tr><th>日期</th><th>上班</th><th>下班</th><th>状态</th><th class="num">迟到</th></tr></thead>
                        <tbody>
                        @forelse ($attendance as $a)
                            <tr>
                                <td>{{ $a->work_date?->toDateString() }}</td>
                                <td>{{ $a->clock_in ?? '—' }}</td>
                                <td>{{ $a->clock_out ?? '—' }}</td>
                                <td><span class="hr-badge b-draft">{{ $a->status }}</span></td>
                                <td class="num">{{ (int) $a->late_minutes }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="color:#8a97a8">本月还没有打卡记录。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-6">
                <div class="hr-card">
                    <h3 style="margin:0 0 10px">我的培训</h3>
                    <table class="hr-table">
                        <thead><tr><th>课程</th><th>场次</th><th>结果</th><th class="num">成绩</th></tr></thead>
                        <tbody>
                        @forelse ($trainings as $t)
                            <tr>
                                <td>{{ $t->session?->course?->title }}</td>
                                <td>{{ $t->session?->session_no }}</td>
                                <td><span class="hr-badge {{ $t->result === 'pass' ? 'b-done' : ($t->result === 'fail' ? 'b-bad' : 'b-draft') }}">{{ $t->result }}</span></td>
                                <td class="num">{{ $t->score ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="color:#8a97a8">还没有培训记录。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="col-6">
                <div class="hr-card">
                    <h3 style="margin:0 0 10px">我的证书</h3>
                    <table class="hr-table">
                        <thead><tr><th>证书</th><th>编号</th><th>到期</th><th>剩余</th></tr></thead>
                        <tbody>
                        @forelse ($certificates as $c)
                            <tr>
                                <td>{{ $c->name }}</td>
                                <td>{{ $c->cert_no ?: '—' }}</td>
                                <td>{{ $c->expire_on?->toDateString() ?? '长期' }}</td>
                                <td>
                                    @if ($c->days_to_expire === null)
                                        <span class="hr-badge b-draft">长期</span>
                                    @elseif ($c->days_to_expire < 0)
                                        <span class="hr-badge b-bad">已过期</span>
                                    @else
                                        <span class="hr-badge {{ $c->days_to_expire <= 60 ? 'b-draft' : 'b-done' }}">剩 {{ $c->days_to_expire }} 天</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="color:#8a97a8">还没有证书记录。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
