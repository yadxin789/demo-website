@csrf
@isset($method)
    @method($method)
@endisset

@php
    $typeLabels = ['storage' => '存储', 'picking' => '拣货', 'receiving' => '收货', 'shipping' => '发货'];
    $type = old('type', $location?->type ?? 'storage');
    $warehouseId = old('warehouse_id', $location?->warehouse_id ?? null);
    $parentId = old('parent_id', $location?->parent_id ?? null);
@endphp

<div class="mb-3">
    <label for="warehouse_id" class="form-label">所属仓库 <span aria-hidden="true">*</span></label>
    <select id="warehouse_id" name="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
        <option value="">请选择仓库</option>
        @foreach ($warehouses as $warehouse)
            <option value="{{ $warehouse->id }}" @selected((int) $warehouseId === $warehouse->id)>{{ $warehouse->name }}</option>
        @endforeach
    </select>
    @error('warehouse_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="parent_id" class="form-label">父级库位</label>
    <select id="parent_id" name="parent_id" class="form-select @error('parent_id') is-invalid @enderror">
        <option value="">无（根库位）</option>
        @foreach ($parents as $parent)
            <option value="{{ $parent->id }}" @selected((int) $parentId === $parent->id)>{{ $parent->code }} · {{ $parent->name }}</option>
        @endforeach
    </select>
    @error('parent_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="row mb-3">
    <div class="col-sm-6">
        <label for="code" class="form-label">编码 <span aria-hidden="true">*</span></label>
        <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror"
               value="{{ old('code', $location?->code ?? '') }}" required maxlength="20"
               autocomplete="off" autocapitalize="none" spellcheck="false">
        @error('code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label for="name" class="form-label">名称 <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $location?->name ?? '') }}" required maxlength="50">
        @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
</div>

<div class="row mb-3">
    <div class="col-sm-6">
        <label for="barcode" class="form-label">库位条码</label>
        <input type="text" id="barcode" name="barcode" class="form-control @error('barcode') is-invalid @enderror"
               value="{{ old('barcode', $location?->barcode ?? '') }}" maxlength="64"
               placeholder="留空自动生成，如 LOC-WH1-A1" autocomplete="off" spellcheck="false">
        @error('barcode')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
        <div class="form-text">用于上架 / 调拨 / 盘点扫码；留空按仓库编码 + 库位编码自动生成。</div>
    </div>
</div>

<div class="mb-3">
    <label for="type" class="form-label">库位类型 <span aria-hidden="true">*</span></label>
    <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
        @foreach ($typeLabels as $value => $label)
            <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
        @endforeach
    </select>
    @error('type')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="form-check mb-3">
    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" value="1"
           @checked(old('is_active', $location?->is_active ?? true))>
    <label for="is_active" class="form-check-label">启用</label>
</div>
