@csrf
@isset($method)
    @method($method)
@endisset

<div class="mb-3">
    <label for="name" class="form-label">名称 <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $warehouse?->name ?? '') }}" required maxlength="50">
    @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="code" class="form-label">编码 <span aria-hidden="true">*</span></label>
    <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror"
           value="{{ old('code', $warehouse?->code ?? '') }}" required maxlength="20"
           autocomplete="off" autocapitalize="none" spellcheck="false">
    @error('code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="address" class="form-label">地址</label>
    <textarea id="address" name="address" class="form-control @error('address') is-invalid @enderror"
              rows="2" maxlength="255">{{ old('address', $warehouse?->address ?? '') }}</textarea>
    @error('address')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="form-check mb-2">
    <input type="checkbox" id="is_default" name="is_default" class="form-check-input" value="1"
           @checked(old('is_default', $warehouse?->is_default ?? false))>
    <label for="is_default" class="form-check-label">设为默认仓库</label>
</div>

<div class="form-check mb-3">
    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" value="1"
           @checked(old('is_active', $warehouse?->is_active ?? true))>
    <label for="is_active" class="form-check-label">启用</label>
</div>
