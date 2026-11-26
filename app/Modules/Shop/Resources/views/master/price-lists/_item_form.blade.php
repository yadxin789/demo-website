@csrf
@isset($item)
    @method('PATCH')
@endisset

@php
    $productId = old('product_id', $item?->product_id ?? null);
@endphp

<div class="row g-2 align-items-end">
    <div class="col-sm-3">
        <label for="product_id" class="form-label">商品 <span aria-hidden="true">*</span></label>
        <select id="product_id" name="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
            <option value="">请选择商品</option>
            @foreach ($products as $product)
                <option value="{{ $product->id }}" @selected((int) $productId === $product->id)>
                    {{ $product->code }} · {{ $product->name }}
                </option>
            @endforeach
        </select>
        @error('product_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-2">
        <label for="min_qty" class="form-label">起订量</label>
        <input type="number" id="min_qty" name="min_qty" class="form-control"
               value="{{ old('min_qty', $item?->min_qty ?? 0) }}" min="0" step="0.0001" inputmode="decimal">
    </div>
    <div class="col-sm-2">
        <label for="price" class="form-label">价格 <span aria-hidden="true">*</span></label>
        <input type="number" id="price" name="price" class="form-control @error('price') is-invalid @enderror"
               value="{{ old('price', $item?->price ?? '') }}" min="0" step="0.0001" inputmode="decimal" required>
        @error('price')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-2">
        <label for="valid_from" class="form-label">生效日期</label>
        <input type="date" id="valid_from" name="valid_from" class="form-control"
               value="{{ old('valid_from', isset($item) && $item->valid_from ? $item->valid_from->toDateString() : '') }}">
    </div>
    <div class="col-sm-2">
        <label for="valid_to" class="form-label">失效日期</label>
        <input type="date" id="valid_to" name="valid_to" class="form-control"
               value="{{ old('valid_to', isset($item) && $item->valid_to ? $item->valid_to->toDateString() : '') }}">
    </div>
    <div class="col-sm-1">
        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-check"></i></button>
    </div>
</div>
@error('valid_to')<div class="invalid-feedback d-block" role="alert">{{ $message }}</div>@enderror
