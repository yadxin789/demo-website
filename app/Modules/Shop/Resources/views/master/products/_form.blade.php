@csrf
@isset($method)
    @method($method)
@endisset

@php
    $traceLabels = ['none' => '不追踪', 'lot' => '批次效期', 'serial' => '序列号'];
    $methodLabels = ['standard' => '标准成本', 'avco' => '移动加权（预留）', 'fifo' => '先进先出（预留）'];
    $categoryId = old('category_id', $product?->category_id ?? null);
    $unitId = old('unit_id', $product?->unit_id ?? null);
    $taxId = old('tax_id', $product?->tax_id ?? null);
    $trace = old('traceability', $product?->traceability ?? 'none');
    $method = old('cost_method', $product?->cost_method ?? 'standard');
@endphp

<fieldset class="mb-4">
    <legend class="h6">基本信息</legend>

    <div class="row mb-3">
        <div class="col-sm-6">
            <label for="code" class="form-label">商品编码 <span aria-hidden="true">*</span></label>
            <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror"
                   value="{{ old('code', $product?->code ?? '') }}" required maxlength="50"
                   autocomplete="off" autocapitalize="none" spellcheck="false">
            @error('code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="col-sm-6">
            <label for="barcode" class="form-label">条码</label>
            <input type="text" id="barcode" name="barcode" class="form-control @error('barcode') is-invalid @enderror"
                   value="{{ old('barcode', $product?->barcode ?? '') }}" maxlength="50"
                   autocomplete="off" autocapitalize="none" spellcheck="false">
            @error('barcode')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="mb-3">
        <label for="name" class="form-label">商品名称 <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $product?->name ?? '') }}" required maxlength="100">
        @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>

    <div class="row mb-3">
        <div class="col-sm-4">
            <label for="category_id" class="form-label">类别</label>
            <select id="category_id" name="category_id" class="form-select">
                <option value="">未分类</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) $categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-4">
            <label for="unit_id" class="form-label">计量单位</label>
            <select id="unit_id" name="unit_id" class="form-select">
                <option value="">请选择单位</option>
                @foreach ($units as $unit)
                    <option value="{{ $unit->id }}" @selected((int) $unitId === $unit->id)>{{ $unit->name }}（{{ $unit->code }}）</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-4">
            <label for="tax_id" class="form-label">税率</label>
            <select id="tax_id" name="tax_id" class="form-select">
                <option value="">不含税</option>
                @foreach ($taxes as $tax)
                    <option value="{{ $tax->id }}" @selected((int) $taxId === $tax->id)>{{ $tax->name }}（{{ rtrim(rtrim(number_format((float) $tax->rate, 4), '0'), '.') }}%）</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mb-3">
        <label for="description" class="form-label">备注说明</label>
        <textarea id="description" name="description" class="form-control" rows="2" maxlength="1000">{{ old('description', $product?->description ?? '') }}</textarea>
    </div>
</fieldset>

<fieldset class="mb-4">
    <legend class="h6">商品主图</legend>

    <div class="row g-3 align-items-start">
        <div class="col-sm-4">
            @if ($product?->hasImage())
                <img src="{{ $product->imageUrl('thumb') }}" alt="商品主图缩略图"
                     width="160" height="160" class="img-thumbnail" style="object-fit:cover;">
            @else
                <div class="d-flex flex-column align-items-center justify-content-center text-muted border rounded"
                     style="inline-size:160px; block-size:160px;" role="img" aria-label="暂无商品图片">
                    <i class="fa-regular fa-image fa-2x" aria-hidden="true"></i>
                    <span class="small mt-2">暂无图片</span>
                </div>
            @endif
        </div>
        <div class="col-sm-8">
            <label for="image" class="form-label">上传图片</label>
            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                   class="form-control @error('image') is-invalid @enderror" aria-describedby="image-help">
            @error('image')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            <p id="image-help" class="form-text">
                支持 JPG、PNG、WebP，单张不超过 5 MB、最长边 6000 像素。图片保存在本机并自动生成缩略图，断网环境同样可以显示，不会引用任何外部地址。
            </p>

            @if ($product?->hasImage())
                <div class="form-check">
                    <input type="checkbox" id="remove_image" name="remove_image" value="1" class="form-check-input">
                    <label for="remove_image" class="form-check-label">移除当前图片（保存后生效）</label>
                </div>
            @endif
        </div>
    </div>
</fieldset>

<fieldset class="mb-4">
    <legend class="h6">价格与成本</legend>

    <div class="row mb-3">
        <div class="col-sm-4">
            <label for="cost_method" class="form-label">成本计价方式</label>
            <select id="cost_method" name="cost_method" class="form-select">
                @foreach ($methodLabels as $value => $label)
                    <option value="{{ $value }}" @selected($method === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="form-text">本期启用标准成本法；其余方式为后续版本预留。</p>
        </div>
        <div class="col-sm-4">
            <label for="standard_cost" class="form-label">标准成本 <span aria-hidden="true">*</span></label>
            <input type="number" id="standard_cost" name="standard_cost" class="form-control @error('standard_cost') is-invalid @enderror"
                   value="{{ old('standard_cost', $product?->standard_cost ?? 0) }}" min="0" step="0.0001" inputmode="decimal" required>
            @error('standard_cost')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="col-sm-4">
            <label for="list_price" class="form-label">零售价 <span aria-hidden="true">*</span></label>
            <input type="number" id="list_price" name="list_price" class="form-control @error('list_price') is-invalid @enderror"
                   value="{{ old('list_price', $product?->list_price ?? 0) }}" min="0" step="0.0001" inputmode="decimal" required>
            @error('list_price')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="mb-3">
        <label for="avg_cost" class="form-label">平均成本（预留）</label>
        <input type="number" id="avg_cost" name="avg_cost" class="form-control"
               value="{{ old('avg_cost', $product?->avg_cost ?? 0) }}" min="0" step="0.0001" inputmode="decimal" readonly>
    </div>
</fieldset>

<fieldset class="mb-4">
    <legend class="h6">批次 / 序列号追踪</legend>

    <div class="row mb-3">
        <div class="col-sm-6">
            <label for="traceability" class="form-label">追溯方式 <span aria-hidden="true">*</span></label>
            <select id="traceability" name="traceability" class="form-select" required>
                @foreach ($traceLabels as $value => $label)
                    <option value="{{ $value }}" @selected($trace === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-6">
            <label for="shelf_life_days" class="form-label">保质期天数</label>
            <input type="number" id="shelf_life_days" name="shelf_life_days" class="form-control"
                   value="{{ old('shelf_life_days', $product?->shelf_life_days ?? '') }}" min="0" inputmode="numeric">
        </div>
    </div>
</fieldset>

<div class="form-check mb-3">
    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" value="1"
           @checked(old('is_active', $product?->is_active ?? true))>
    <label for="is_active" class="form-check-label">启用该商品</label>
</div>
