@extends('shop::layouts.app')

@section('title', '编辑编号规则')
@section('heading', '编辑编号规则')

@section('content')
    @php
        $periodLabels = ['none' => '不重置', 'day' => '按日重置', 'month' => '按月重置', 'year' => '按年重置'];
        $mode = old('period_mode', $sequence->period_mode ?? 'none');
    @endphp

    <div class="card" style="max-inline-size:36rem;">
        <form method="post" action="{{ route('shop.sequences.update', $sequence) }}" aria-label="编辑编号规则表单">
            @csrf
            @method('PATCH')

            <div class="mb-3">
                <label for="name" class="form-label">规则名称 <span aria-hidden="true">*</span></label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name', $sequence->name) }}" required maxlength="50">
                @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>

            <div class="row mb-3">
                <div class="col-sm-6">
                    <label for="prefix" class="form-label">前缀</label>
                    <input type="text" id="prefix" name="prefix" class="form-control"
                           value="{{ old('prefix', $sequence->prefix) }}" maxlength="20"
                           autocomplete="off" spellcheck="false">
                </div>
                <div class="col-sm-6">
                    <label for="suffix" class="form-label">后缀</label>
                    <input type="text" id="suffix" name="suffix" class="form-control"
                           value="{{ old('suffix', $sequence->suffix) }}" maxlength="20"
                           autocomplete="off" spellcheck="false">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-6">
                    <label for="padding" class="form-label">流水位数 <span aria-hidden="true">*</span></label>
                    <input type="number" id="padding" name="padding" class="form-control @error('padding') is-invalid @enderror"
                           value="{{ old('padding', $sequence->padding) }}" min="1" max="10" inputmode="numeric" required>
                    @error('padding')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="period_mode" class="form-label">期间重置</label>
                    <select id="period_mode" name="period_mode" class="form-select">
                        @foreach ($periodLabels as $value => $label)
                            <option value="{{ $value }}" @selected($mode === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <p class="form-text">当前下一流水号：<code>{{ $sequence->next_number }}</code>（由系统自动递增，不在此手工修改）</p>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存修改</button>
                <a href="{{ route('shop.sequences.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
