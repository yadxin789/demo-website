@csrf
@isset($method)
    @method($method)
@endisset

<div class="row mb-3">
    <div class="col-sm-6">
        <label for="code" class="form-label">编码 <span aria-hidden="true">*</span></label>
        <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $supplier?->code ?? '') }}" required maxlength="20"
               autocomplete="off" autocapitalize="none" spellcheck="false">
        @error('code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label for="name" class="form-label">名称 <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $supplier?->name ?? '') }}" required maxlength="100">
        @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-sm-6">
        <label for="contact_name" class="form-label">联系人</label>
        <input type="text" id="contact_name" name="contact_name" class="form-control"
               value="{{ old('contact_name', $supplier?->contact_name ?? '') }}" maxlength="50">
    </div>
    <div class="col-sm-6">
        <label for="phone" class="form-label">电话</label>
        <input type="text" id="phone" name="phone" class="form-control"
               value="{{ old('phone', $supplier?->phone ?? '') }}" maxlength="30" inputmode="tel">
    </div>
</div>

<div class="mb-3">
    <label for="email" class="form-label">邮箱</label>
    <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
           value="{{ old('email', $supplier?->email ?? '') }}" maxlength="100">
    @error('email')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="address" class="form-label">地址</label>
    <textarea id="address" name="address" class="form-control" rows="2" maxlength="255">{{ old('address', $supplier?->address ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="tax_no" class="form-label">税号 / 统一社会信用代码</label>
    <input type="text" id="tax_no" name="tax_no" class="form-control"
           value="{{ old('tax_no', $supplier?->tax_no ?? '') }}" maxlength="30">
</div>

<div class="form-check mb-3">
    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" value="1"
           @checked(old('is_active', $supplier?->is_active ?? true))>
    <label for="is_active" class="form-check-label">启用</label>
</div>
