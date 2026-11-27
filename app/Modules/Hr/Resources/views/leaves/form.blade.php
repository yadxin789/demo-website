@extends('hr::layouts.app')

@section('title', '提交请假')
@section('sub', '提交后单号自动生成，天数按自然日含首尾计算')

@section('content')
    <div class="hr-card" style="max-width:880px">
        <h3>请假申请 <span class="hr-badge b-draft">{{ $nextNo }}</span></h3>

        <form method="post" action="{{ route('hr.leaves.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">员工</label>
                    <select class="form-select" name="employee_id" required>
                        <option value="">选择员工</option>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}" @selected((int) old('employee_id') === $e->id)>{{ $e->employee_no }} {{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">请假类型</label>
                    <select class="form-select" name="type" required>
                        @foreach ($typeText as $k => $v)
                            <option value="{{ $k }}" @selected(old('type') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">半天假</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" id="half" name="half_day" type="checkbox" value="1" @checked(old('half_day'))>
                        <label class="form-check-label" for="half">当天只请半天（记 0.5 天）</label>
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label">开始日期</label>
                    <input class="form-control" name="start_date" id="startDate" type="date"
                           value="{{ old('start_date', $today) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">结束日期</label>
                    <input class="form-control" name="end_date" id="endDate" type="date"
                           value="{{ old('end_date', $today) }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">预计天数</label>
                    <input class="form-control" id="daysPreview" value="1" readonly>
                    <div class="form-text">按自然日含首尾自动算</div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">审批后扣减</label>
                    <input class="form-control" id="deductHint" value="年假 / 调休" readonly>
                </div>

                <div class="col-12">
                    <label class="form-label">请假事由</label>
                    <input class="form-control" name="reason" value="{{ old('reason') }}" placeholder="家中有事 / 就医 / 年假出游">
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger py-2 mt-3">
                    @foreach ($errors->all() as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">提交申请</button>
                <a class="btn btn-outline-secondary" href="{{ route('hr.leaves.index') }}">返回列表</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
<script>
    const start = document.getElementById('startDate');
    const end = document.getElementById('endDate');
    const half = document.getElementById('half');
    const preview = document.getElementById('daysPreview');
    const typeSel = document.querySelector('select[name="type"]');
    const deduct = document.getElementById('deductHint');

    function recalc() {
        const s = new Date(start.value);
        const e = new Date(end.value);
        if (isNaN(s) || isNaN(e) || e < s) {
            preview.value = '—';
            return;
        }
        const diff = Math.round((e - s) / 86400000);
        let days = diff + 1;
        if (diff === 0 && half.checked) {
            days = 0.5;
        }
        preview.value = days;
        deduct.value = (typeSel.value === 'annual' || typeSel.value === 'compensatory') ? '扣年假/调休余额' : '不扣余额';
    }

    start.addEventListener('change', recalc);
    end.addEventListener('change', recalc);
    half.addEventListener('change', recalc);
    typeSel.addEventListener('change', recalc);
    recalc();
</script>
@endpush
