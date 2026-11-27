@extends('hr::layouts.app')

@section('title', '技能矩阵')
@section('sub', '员工 × 技能等级，一眼看出谁能上哪台设备、哪个工种缺人')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-3"><div class="hr-card"><div style="color:#6f6f80;font-size:12px">技能项</div><div style="font-size:24px;font-weight:700">{{ $stat['skills'] }}</div></div></div>
        <div class="col-3"><div class="hr-card"><div style="color:#6f6f80;font-size:12px">技能记录</div><div style="font-size:24px;font-weight:700">{{ $stat['holders'] }}</div></div></div>
        <div class="col-3"><div class="hr-card"><div style="color:#6f6f80;font-size:12px">平均等级</div><div style="font-size:24px;font-weight:700">{{ number_format($stat['avg_level'], 2) }}</div></div></div>
        <div class="col-3"><div class="hr-card"><div style="color:#6f6f80;font-size:12px">证书</div><div style="font-size:24px;font-weight:700">{{ $stat['certificates'] }}</div></div></div>
    </div>

    <div class="hr-card mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
            <h3 style="margin:0">技能矩阵</h3>
            <form method="get" class="d-flex gap-2">
                <select class="form-select form-select-sm" name="dept" style="width:160px">
                    <option value="">全部部门</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected((string) $d->id === $dept)>{{ $d->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-outline-primary">筛选</button>
            </form>
        </div>

        <div style="overflow-x:auto">
            <table class="hr-table">
                <thead>
                <tr>
                    <th style="min-width:150px">员工</th>
                    @foreach ($skills as $s)
                        <th class="num" title="{{ $s->name }}">
                            {{ $s->code }}
                            <div style="font-weight:400;font-size:11px;color:#8a97a8">{{ $s->name }}</div>
                            <div style="font-weight:400;font-size:11px;color:#adb5bd">
                                均 {{ isset($skillAvg[$s->id]) ? number_format((float) $skillAvg[$s->id]->avg_level, 1) : '—' }}
                            </div>
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @forelse ($employees as $e)
                    <tr>
                        <td>{{ $e->employee_no }} {{ $e->name }}<div style="font-size:11px;color:#8a97a8">{{ $e->department?->name }}</div></td>
                        @foreach ($skills as $s)
                            @php($lv = $e->skills->firstWhere('skill_id', $s->id)?->level)
                            <td class="num">
                                @if ($lv)
                                    <span class="hr-badge {{ $lv >= 4 ? 'b-done' : ($lv >= 2 ? 'b-draft' : 'b-bad') }}">{{ $lv }}</span>
                                @else
                                    <span style="color:#dee2e6">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ $skills->count() + 1 }}" style="color:#8a97a8">没有在职员工。</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="font-size:12px;color:#8a97a8;margin-top:8px">等级 1–5：4 级以上可独立顶岗（绿），2–3 级需带班（灰），1 级见习（红）。</div>
    </div>

    <div class="row g-3">
        <div class="col-6">
            <div class="hr-card">
                <h3 style="margin:0 0 10px">记录员工技能等级</h3>
                <form method="post" action="{{ route('hr.skills.assign') }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-6">
                            <select class="form-select form-select-sm" name="employee_id" required>
                                <option value="">员工</option>
                                @foreach ($employees as $e)
                                    <option value="{{ $e->id }}">{{ $e->employee_no }} {{ $e->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <select class="form-select form-select-sm" name="skill_id" required>
                                <option value="">技能</option>
                                @foreach ($skills as $s)
                                    <option value="{{ $s->id }}">{{ $s->code }} {{ $s->name }}（≤{{ $s->max_level }}）</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-3">
                            <select class="form-select form-select-sm" name="level">
                                @for ($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}">{{ $i }} 级</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-5"><input class="form-control form-control-sm" name="remark" placeholder="评定说明（可空）"></div>
                        <div class="col-4"><button class="btn btn-primary btn-sm w-100">保存</button></div>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-6">
            <div class="hr-card">
                <h3 style="margin:0 0 10px">新建技能项</h3>
                <form method="post" action="{{ route('hr.skills.store') }}">
                    @csrf
                    <div class="row g-2">
                        <div class="col-4"><input class="form-control form-control-sm" name="code" placeholder="SK-001" required></div>
                        <div class="col-8"><input class="form-control form-control-sm" name="name" placeholder="技能名称" required></div>
                        <div class="col-6"><input class="form-control form-control-sm" name="category" value="操作技能"></div>
                        <div class="col-3"><input class="form-control form-control-sm" type="number" name="max_level" value="5" min="3" max="9"></div>
                        <div class="col-3"><button class="btn btn-primary btn-sm w-100">新增</button></div>
                    </div>
                </form>
                <div class="mt-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('hr.skills.certificates') }}">去证书台账（含到期提醒）</a></div>
            </div>
        </div>
    </div>
@endsection
