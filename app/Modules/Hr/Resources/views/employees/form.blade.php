@extends('hr::layouts.app')

@section('title', $employee ? '改员工档案' : '新增员工')
@section('sub', $employee ? $employee->employee_no . ' · ' . $employee->name : '入职建档会自动生成工号与首份合同')

@section('content')
    <div class="hr-card" style="max-width: 960px">
        <h3>{{ $employee ? '修改档案' : '入职建档' }}</h3>

        <form method="post" action="{{ $employee ? route('hr.employees.update', $employee->id) : route('hr.employees.store') }}">
            @csrf
            @if ($employee)
                @method('put')
            @endif

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">工号</label>
                    <input class="form-control" name="employee_no" value="{{ old('employee_no', $employee?->employee_no ?? $nextNo) }}"
                           @if ($employee) readonly @endif>
                    <div class="form-text">{{ $employee ? '工号建立后不可改' : '留空或按建议值，重复会报错' }}</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">姓名</label>
                    <input class="form-control" name="name" value="{{ old('name', $employee?->name) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">性别</label>
                    <select class="form-select" name="gender">
                        <option value="female" @selected(old('gender', $employee?->gender ?? 'female') === 'female')>女</option>
                        <option value="male" @selected(old('gender', $employee?->gender ?? 'female') === 'male')>男</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">部门</label>
                    <select class="form-select" name="department_id" required>
                        <option value="">选择部门</option>
                        @foreach ($options['departments'] as $d)
                            <option value="{{ $d->id }}" @selected((int) old('department_id', $employee?->department_id) === $d->id)>
                                {{ $d->code }} {{ $d->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">岗位</label>
                    <select class="form-select" name="position_id" required>
                        <option value="">选择岗位</option>
                        @foreach ($options['positions'] as $p)
                            <option value="{{ $p->id }}" @selected((int) old('position_id', $employee?->position_id) === $p->id)>
                                {{ $p->code }} {{ $p->name }}@if ($p->grade) （{{ $p->grade }}）@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">入职日期</label>
                    <input class="form-control" name="hire_date" type="date"
                           value="{{ old('hire_date', $employee?->hire_date?->toDateString() ?? now()->toDateString()) }}" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">状态</label>
                    <select class="form-select" name="status">
                        @foreach ($options['statusText'] as $k => $v)
                            <option value="{{ $k }}" @selected(old('status', $employee?->status ?? 'probation') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">生日</label>
                    <input class="form-control" name="birthday" type="date" value="{{ old('birthday', $employee?->birthday?->toDateString()) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">学历</label>
                    <input class="form-control" name="education" value="{{ old('education', $employee?->education) }}" placeholder="本科 / 硕士">
                </div>

                <div class="col-md-4">
                    <label class="form-label">手机</label>
                    <input class="form-control" name="phone" value="{{ old('phone', $employee?->phone) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">邮箱</label>
                    <input class="form-control" name="email" type="email" value="{{ old('email', $employee?->email) }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">身份证（脱敏）</label>
                    <input class="form-control" name="id_card_masked" value="{{ old('id_card_masked', $employee?->id_card_masked) }}"
                           placeholder="310***1990***1234">
                </div>

                <div class="col-12">
                    <label class="form-label">备注</label>
                    <input class="form-control" name="remark" value="{{ old('remark', $employee?->remark) }}">
                </div>
            </div>

            @unless ($employee)
                <hr class="my-4">
                <h4 style="font-size:15px">首份合同（可选，留空则不建）</h4>
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">合同类型</label>
                        <select class="form-select" name="contract_type">
                            <option value="">不建合同</option>
                            <option value="fixed" @selected(old('contract_type') === 'fixed')>固定期限</option>
                            <option value="open" @selected(old('contract_type') === 'open')>无固定期限</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">起始日</label>
                        <input class="form-control" name="contract_start" type="date" value="{{ old('contract_start') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">到期日</label>
                        <input class="form-control" name="contract_end" type="date" value="{{ old('contract_end') }}">
                        <div class="form-text">固定期限不填则默认三年</div>
                    </div>
                </div>
            @endunless

            @if ($errors->any())
                <div class="alert alert-danger py-2 mt-3">
                    @foreach ($errors->all() as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <div class="d-flex gap-2 mt-4">
                <button class="btn btn-primary" type="submit">{{ $employee ? '保存修改' : '建档入职' }}</button>
                <a class="btn btn-outline-secondary" href="{{ $employee ? route('hr.employees.show', $employee->id) : route('hr.employees.index') }}">取消</a>
            </div>
        </form>
    </div>
@endsection
