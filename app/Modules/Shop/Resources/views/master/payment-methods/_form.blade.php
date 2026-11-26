@csrf
@isset($method)
    @method($method)
@endisset

@php
    $typeLabels = [
        'cash' => '现金',
        'bank' => '银行存款',
        'electronic' => '电子支付',
        'card' => '银行卡',
        'coupon' => '储值券',
    ];
    $type = old('type', $payment_method?->type ?? 'cash');
@endphp

<div class="mb-3">
    <label for="name" class="form-label">名称 <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $payment_method?->name ?? '') }}" required maxlength="50">
    @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="code" class="form-label">编码 <span aria-hidden="true">*</span></label>
    <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror"
           value="{{ old('code', $payment_method?->code ?? '') }}" required maxlength="20"
           autocomplete="off" autocapitalize="none" spellcheck="false">
    @error('code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="type" class="form-label">类型 <span aria-hidden="true">*</span></label>
    <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
        @foreach ($typeLabels as $value => $label)
            <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('type')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="account_id" class="form-label">对账科目 ID</label>
    <input type="number" id="account_id" name="account_id" class="form-control"
           value="{{ old('account_id', $payment_method?->account_id ?? '') }}" min="1" inputmode="numeric"
           placeholder="科目表建成后回填">
</div>

<div class="form-check mb-2">
    <input type="checkbox" id="is_default" name="is_default" class="form-check-input" value="1"
           @checked(old('is_default', $payment_method?->is_default ?? false))>
    <label for="is_default" class="form-check-label">设为默认支付方式</label>
</div>

<div class="form-check mb-3">
    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" value="1"
           @checked(old('is_active', $payment_method?->is_active ?? true))>
    <label for="is_active" class="form-check-label">启用</label>
</div>
