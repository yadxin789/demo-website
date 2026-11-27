@extends('hr::layouts.app')

@section('title', '人力看板')
@section('sub', '华瑞集团（控股）· 今天要处理的事')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi purple">
            <div class="lbl">在职人数</div>
            <div class="num">{{ $k['active'] }}</div>
            <div class="hint">试用 {{ $k['probation'] }} · 离职 {{ $k['left'] }}</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">本月入职 / 离职</div>
            <div class="num">{{ $k['hired_month'] }} / {{ $k['left_month'] }}</div>
            <div class="hint">截至 {{ now()->format('m-d') }}</div>
        </div>
        <div class="hr-kpi warn">
            <div class="lbl">待审请假</div>
            <div class="num">{{ $k['pending_leaves'] }}</div>
            <div class="hint">待审加班 {{ $k['pending_overtimes'] }} 条</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">考勤异常待处理</div>
            <div class="num">{{ $k['open_exceptions'] }}</div>
            <div class="hint">缺卡 / 迟到 / 早退</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">合同 30 天内到期</div>
            <div class="num">{{ $k['contracts_expiring'] }}</div>
            <div class="hint">到期前 30 天提醒</div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="hr-card mb-3">
                <h3>部门在职分布</h3>
                @forelse ($k['departments'] as $d)
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px">
                        <div style="width:120px; color:#6f6f80">{{ $d['name'] }}</div>
                        <div class="hr-progress" style="flex:1"><span style="width: {{ $d['pct'] }}%"></span></div>
                        <div style="width:48px; text-align:right; font-variant-numeric: tabular-nums">{{ $d['count'] }}</div>
                    </div>
                @empty
                    <div class="hr-empty">还没有员工数据，先跑种子。</div>
                @endforelse
            </div>

            <div class="hr-card">
                <h3>考勤异常待处理 · {{ $k['open_exceptions'] }} 条</h3>
                <table class="hr-table">
                    <thead>
                    <tr><th>工号</th><th>姓名</th><th>日期</th><th>类型</th><th></th></tr>
                    </thead>
                    <tbody>
                    @forelse ($k['exception_rows'] as $e)
                        <tr>
                            <td>{{ $e->employee?->employee_no }}</td>
                            <td>{{ $e->employee?->name }}</td>
                            <td>{{ $e->work_date?->format('m-d') }}</td>
                            <td>
                                @php($t = ['missing' => '缺卡', 'late' => '迟到', 'early' => '早退', 'absent' => '旷工'][$e->type] ?? $e->type)
                                <span class="hr-badge b-progress">{{ $t }}</span>
                            </td>
                            <td><a href="{{ route('hr.attendance.exceptions') }}" class="text-decoration-none">去处理</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="hr-empty">没有待处理异常，考勤很干净。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="hr-card mb-3">
                <h3>待审请假 · {{ $k['pending_leaves'] }} 条</h3>
                @forelse ($k['pending_leave_rows'] as $l)
                    <div style="display:flex; justify-content:space-between; align-items:center; padding:9px 0; border-bottom:1px dashed #eeeaf7">
                        <div>
                            <div style="font-weight:600">{{ $l->employee?->name }} <span style="color:#6f6f80; font-size:12px">{{ $l->employee?->employee_no }}</span></div>
                            <div style="font-size:12px; color:#6f6f80">
                                {{ ['annual'=>'年假','sick'=>'病假','personal'=>'事假','compensatory'=>'调休'][$l->type] ?? $l->type }}
                                {{ $l->start_date?->format('m-d') }} ~ {{ $l->end_date?->format('m-d') }} · {{ $l->days }} 天
                            </div>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.leaves.index') }}">审批</a>
                    </div>
                @empty
                    <div class="hr-empty">没有待审请假。</div>
                @endforelse
            </div>

            <div class="hr-card mb-3">
                <h3>合同到期提醒</h3>
                @forelse ($k['expiring_rows'] as $c)
                    <div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed #eeeaf7; font-size:13px">
                        <span>{{ $c->employee?->name }} <span style="color:#6f6f80">{{ $c->employee?->employee_no }}</span></span>
                        <span class="{{ $c->end_date && $c->end_date->diffInDays(now()) <= 30 ? 'text-danger' : 'text-muted' }}">
                            {{ $c->end_date?->format('Y-m-d') }}
                        </span>
                    </div>
                @empty
                    <div class="hr-empty">60 天内没有合同到期。</div>
                @endforelse
            </div>

            <div class="hr-card">
                <h3>本月工资</h3>
                @if ($k['latest_run'])
                    <div style="font-size:13px; line-height:2">
                        批次：<b>{{ $k['latest_run']->payroll_no }}</b>
                        <span class="hr-badge {{ $k['latest_run']->status === 'paid' ? 'b-done' : ($k['latest_run']->status === 'confirmed' ? 'b-released' : 'b-draft') }}">
                            {{ ['draft'=>'草稿','confirmed'=>'已确认','paid'=>'已发放'][$k['latest_run']->status] ?? $k['latest_run']->status }}
                        </span><br>
                        人数：{{ $k['latest_run']->employee_count }} 人 · 总额：¥{{ number_format((float) $k['latest_run']->total_amount, 2) }}
                    </div>
                    <a class="btn btn-sm btn-primary mt-2" href="{{ route('hr.payroll.runs.index') }}">去看工资批次</a>
                @else
                    <div class="hr-empty">还没有工资批次。</div>
                @endif
            </div>
        </div>
    </div>

    <div class="hr-card mt-3">
        <h3>招聘在跑</h3>
        <div style="display:flex; gap:26px; font-size:13px; color:#6f6f80">
            <span>在招需求 <b style="color:#22222e">{{ $k['open_reqs'] }}</b> 个</span>
            <span>候选人 <b style="color:#22222e">{{ $k['candidates'] }}</b> 人</span>
            <span>岗位 <b style="color:#22222e">{{ $k['positions'] }}</b> 个</span>
            <a href="{{ route('hr.recruit.requisitions') }}" class="text-decoration-none">去看招聘漏斗 →</a>
        </div>
    </div>
@endsection
