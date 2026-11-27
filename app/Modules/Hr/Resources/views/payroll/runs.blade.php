@extends('hr::layouts.app')

@section('title', '工资批次')
@section('sub', '按月生成草稿，确认后标记发放')

@section('content')
    <div class="hr-card mb-3">
        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <h3 style="margin:0">工资批次（{{ $rows->count() }} 批）</h3>
                <div style="color:#6f6f80; font-size:12px; margin-top:4px">
                    已设薪资档的岗位 {{ $structureCount }} 个；没有档位的岗位不会被算进工资单。
                </div>
            </div>
            <div class="col-md-7">
                <form class="d-flex gap-2 justify-content-end" method="post" action="{{ route('hr.payroll.runs.generate') }}">
                    @csrf
                    <input class="form-control form-control-sm" name="period" type="month" value="{{ $period }}" style="max-width:160px">
                    <button class="btn btn-sm btn-primary" type="submit">生成当月工资单</button>
                </form>
            </div>
        </div>
    </div>

    <div class="hr-card">
        <table class="hr-table">
            <thead>
            <tr><th>期间</th><th>工资单号</th><th class="num">人数</th><th class="num">实发合计</th><th>状态</th><th>生成时间</th><th>发放时间</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td><b>{{ $r->period }}</b></td>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $r->payroll_no }}</td>
                    <td class="num">{{ (int) ($itemsCount[$r->id] ?? $r->employee_count) }}</td>
                    <td class="num" style="font-weight:600">¥{{ number_format((float) $r->total_amount, 2) }}</td>
                    <td>
                        @php($map = ['draft' => 'b-draft', 'confirmed' => 'b-progress', 'paid' => 'b-done'])
                        <span class="hr-badge {{ $map[$r->status] ?? 'b-draft' }}">{{ $statusText[$r->status] ?? $r->status }}</span>
                    </td>
                    <td style="font-size:12px">{{ $r->generated_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td style="font-size:12px">{{ $r->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.payroll.runs.show', $r->id) }}">看明细</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="hr-empty">还没有工资批次，用上面的月份生成一批。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
