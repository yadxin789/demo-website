@csrf
@isset($method)
    @method($method)
@endisset

@php
    $levelLabels = ['normal' => '普通', 'silver' => '银卡', 'gold' => '金卡'];
    $level = old('membership_level', $customer?->membership_level ?? 'normal');
@endphp

<div class="row mb-3">
    <div class="col-sm-6">
        <label for="code" class="form-label">编码 <span aria-hidden="true">*</span></label>
        <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $customer?->code ?? '') }}" required maxlength="20"
               autocomplete="off" autocapitalize="none" spellcheck="false">
        @error('code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label for="name" class="form-label">名称 <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $customer?->name ?? '') }}" required maxlength="100">
        @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-sm-6">
        <label for="contact_name" class="form-label">联系人</label>
        <input type="text" id="contact_name" name="contact_name" class="form-control"
               value="{{ old('contact_name', $customer?->contact_name ?? '') }}" maxlength="50">
    </div>
    <div class="col-sm-6">
        <label for="phone" class="form-label">电话</label>
        <input type="text" id="phone" name="phone" class="form-control"
               value="{{ old('phone', $customer?->phone ?? '') }}" maxlength="30" inputmode="tel">
    </div>
</div>

<div class="mb-3">
    <label for="email" class="form-label">邮箱</label>
    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
           value="{{ old('email', $customer?->email ?? '') }}" maxlength="100">
    @error('email')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="address" class="form-label">地址</label>
    <textarea id="address" name="address" class="form-control" rows="2" maxlength="255">{{ old('address', $customer?->address ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="tax_no" class="form-label">税号</label>
    <input type="text" id="tax_no" name="tax_no" class="form-control"
           value="{{ old('tax_no', $customer?->tax_no ?? '') }}" maxlength="30">
</div>

<div class="row mb-3">
    <div class="col-sm-6">
        <label for="membership_level" class="form-label">会员等级 <span aria-hidden="true">*</span></label>
        <select id="membership_level" name="membership_level" class="form-select" required>
            @foreach ($levelLabels as $value => $label)
                <option value="{{ $value }}" @selected($level === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-sm-6">
        <label for="credit_limit" class="form-label">信用额度</label>
        <input type="number" id="credit_limit" name="credit_limit" class="form-control @error('credit_limit') is-invalid @enderror"
               value="{{ old('credit_limit', $customer?->credit_limit ?? 0) }}" min="0" step="0.01" inputmode="decimal">
        @error('credit_limit')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
</div>

<div class="form-check mb-3">
    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" value="1"
           @checked(old('is_active', $customer?->is_active ?? true))>
    <label for="is_active" class="form-check-label">启用</label>
</div>
