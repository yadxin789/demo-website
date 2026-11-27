@extends('hr::layouts.app')

@section('title', '绩效周期')
@section('sub', '先定周期，再配指标，最后评分')

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="hr-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 style="margin:0">周期</h3>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#cycleModal" type="button">新建</button>
                </div>
                <table class="hr-table">
                    <thead><tr><th>周期</th><th>状态</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($cycles as $c)
                        <tr @if ($c->id === $currentId) style="background:#f4f1fd" @endif>
                            <td>
                                <b>{{ $c->period }}</b>
                                <div style="font-size:11px; color:#9a9aad">{{ $c->start_date?->format('Y-m-d') }} ~ {{ $c->end_date?->format('Y-m-d') }}</div>
                            </td>
                            <td>
                                <span class="hr-badge {{ $c->status === 'closed' ? 'b-done' : 'b-progress' }}">
                                    {{ $statusText[$c->status] ?? $c->status }}
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.performance.cycles', ['cycle' => $c->id]) }}">查看</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="hr-empty">还没有绩效周期。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col-lg-8">
            @if ($current)
                <div class="hr-card mb-3">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <h3 style="margin:0 0 4px">{{ $current->name }}</h3>
                            <div style="color:#6f6f80; font-size:13px">
                                {{ $current->period }} ｜ {{ $current->start_date?->format('Y-m-d') }} ~ {{ $current->end_date?->format('Y-m-d') }}
                                ｜ 已完成 {{ $reviewStats['done'] }} / {{ $reviewStats['total'] }} 人
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.performance.reviews', ['cycle' => $current->id]) }}">去评分</a>
                            @if ($current->status !== 'closed')
                                <form method="post" action="{{ route('hr.performance.cycles.close', $current->id) }}" style="margin:0"
                                      onsubmit="return confirm('结束周期后评分锁定，确定？')">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">结束周期</button>
                                </form>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3 p-2" style="background:{{ abs($weightTotal - 100) < 0.01 ? '#ebf9f2' : '#fff4e6' }}; border-radius:8px; font-size:13px">
                        指标权重合计 <b>{{ $weightTotal }}%</b>
                        @if (abs($weightTotal - 100) < 0.01)
                            <span style="color:#0ca678">符合要求（=100%）</span>
                        @else
                            <span style="color:#d9480f">应等于 100%，还差 {{ round(100 - $weightTotal, 2) }}%</span>
                        @endif
                    </div>
                </div>

                <div class="hr-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h3 style="margin:0">指标（{{ $indicators->count() }} 项）</h3>
                        @if ($current->status !== 'closed')
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#indModal" type="button">加指标</button>
                        @endif
                    </div>
                    <table class="hr-table">
                        <thead><tr><th>指标</th><th class="num">权重</th><th>目标值</th><th>范围</th><th class="num">已评</th><th class="num">平均上级分</th></tr></thead>
                        <tbody>
                        @forelse ($indicators as $ind)
                            @php($st = $indicatorStats[$ind->id] ?? null)
                            <tr>
                                <td><b>{{ $ind->name }}</b></td>
                                <td class="num">{{ rtrim(rtrim(number_format((float) $ind->weight, 2), '0'), '.') }}%</td>
                                <td style="font-size:12px; color:#6f6f80">{{ $ind->target ?? '—' }}</td>
                                <td>{{ $ind->scope }}</td>
                                <td class="num">{{ (int) ($st->rated ?? 0) }}</td>
                                <td class="num" style="font-weight:600">{{ $st->avg_score ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="hr-empty">这个周期还没有指标，先加一条（5 条加起来正好 100%）。</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <div class="hr-card"><div class="hr-empty">还没有绩效周期，点左边"新建"开一个。</div></div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="cycleModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" action="{{ route('hr.performance.cycles.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">新建绩效周期</h5>
                    <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label">周期名称</label>
                        <input class="form-control" name="name" maxlength="30" required placeholder="例：2027 年第一季度绩效">
                    </div>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label">周期编码</label>
                            <input class="form-control" name="period" maxlength="10" required placeholder="例：2027Q1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">开始日期</label>
                            <input class="form-control" name="start_date" type="date" required value="{{ now()->startOfQuarter()->toDateString() }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label">结束日期</label>
                            <input class="form-control" name="end_date" type="date" required value="{{ now()->endOfQuarter()->toDateString() }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">取消</button>
                    <button class="btn btn-primary" type="submit">创建</button>
                </div>
            </form>
        </div>
    </div>

    @if ($current)
        <div class="modal fade" id="indModal" tabindex="-1">
            <div class="modal-dialog">
                <form class="modal-content" method="post" action="{{ route('hr.performance.indicators.store', $current->id) }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">加指标 · {{ $current->period }}</h5>
                        <button class="btn-close" data-bs-dismiss="modal" type="button"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label">指标名称</label>
                            <input class="form-control" name="name" maxlength="20" required placeholder="例：客户满意度">
                        </div>
                        <div class="row g-2">
                            <div class="col-4">
                                <label class="form-label">权重 %</label>
                                <input class="form-control" name="weight" type="number" min="1" max="100" step="1" value="10" required>
                            </div>
                            <div class="col-8">
                                <label class="form-label">适用范围</label>
                                <input class="form-control" name="scope" maxlength="20" value="通用">
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="form-label">目标值</label>
                            <input class="form-control" name="target" maxlength="80" placeholder="例：客户投诉 ≤ 2 起/月">
                        </div>
                        <div style="color:#9a9aad; font-size:12px; margin-top:8px">
                            当前权重合计 {{ $weightTotal }}%，加完不能超过 100%。
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" data-bs-dismiss="modal" type="button">取消</button>
                        <button class="btn btn-primary" type="submit">添加</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
