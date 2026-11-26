@csrf
@isset($method)
    @method($method)
@endisset

<div class="mb-3">
    <label for="name" class="form-label">名称 <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $unit?->name ?? '') }}" required maxlength="50">
    @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="code" class="form-label">编码 <span aria-hidden="true">*</span></label>
    <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror"
           value="{{ old('code', $unit?->code ?? '') }}" required maxlength="20"
           autocomplete="off" autocapitalize="none" spellcheck="false">
    @error('code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="precision" class="form-label">数量小数位</label>
    <input type="number" id="precision" name="precision" class="form-control"
           value="{{ old('precision', $unit?->precision ?? 0) }}" min="0" max="4" inputmode="numeric">
</div>

<div class="form-check mb-3">
    <input type="checkbox" id="is_base" name="is_base" class="form-check-input" value="1"
           @checked(old('is_base', $unit?->is_base ?? false))>
    <label for="is_base" class="form-check-label">设为基础单位</label>
</div>
