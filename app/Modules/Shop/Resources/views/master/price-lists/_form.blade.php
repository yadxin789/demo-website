@csrf
@isset($method)
    @method($method)
@endisset

@php
    $type = old('type', $price_list?->type ?? 'sale');
@endphp

<div class="mb-3">
    <label for="name" class="form-label">名称 <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $price_list?->name ?? '') }}" required maxlength="100">
    @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="row mb-3">
    <div class="col-sm-6">
        <label for="type" class="form-label">类型 <span aria-hidden="true">*</span></label>
        <select id="type" name="type" class="form-select" required>
            <option value="sale" @selected($type === 'sale')>售价表</option>
            <option value="purchase" @selected($type === 'purchase')>采购价表</option>
        </select>
    </div>
    <div class="col-sm-6">
        <label for="currency" class="form-label">币种</label>
        <input type="text" id="currency" name="currency" class="form-control"
               value="{{ old('currency', $price_list?->currency ?? 'CNY') }}" maxlength="3"
               autocomplete="off" spellcheck="false">
    </div>
</div>

<div class="form-check mb-3">
    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" value="1"
           @checked(old('is_active', $price_list?->is_active ?? true))>
    <label for="is_active" class="form-check-label">启用该价格表</label>
</div>
