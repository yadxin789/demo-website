@csrf
@isset($method)
    @method($method)
@endisset

<div class="mb-3">
    <label for="parent_id" class="form-label">上级类别</label>
    <select id="parent_id" name="parent_id" class="form-select">
        <option value="">— 作为顶级类别 —</option>
        @foreach ($parents as $parent)
            <option value="{{ $parent->id }}"
                @selected(old('parent_id', $category?->parent_id ?? '') == $parent->id)>
                {{ $parent->name }}
            </option>
        @endforeach
    </select>
</div>

<div class="mb-3">
    <label for="code" class="form-label">编码 <span aria-hidden="true">*</span></label>
    <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror"
           value="{{ old('code', $category?->code ?? '') }}" required maxlength="50"
           autocomplete="off" autocapitalize="none" spellcheck="false">
    @error('code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="name" class="form-label">名称 <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $category?->name ?? '') }}" required maxlength="100">
    @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="description" class="form-label">备注</label>
    <textarea id="description" name="description" class="form-control" rows="3" maxlength="500">{{ old('description', $category?->description ?? '') }}</textarea>
</div>

<div class="mb-3">
    <label for="sort" class="form-label">排序</label>
    <input type="number" id="sort" name="sort" class="form-control"
           value="{{ old('sort', $category?->sort ?? 0) }}" min="0" inputmode="numeric">
</div>

<div class="form-check mb-3">
    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" value="1"
           @checked(old('is_active', $category?->is_active ?? true))>
    <label for="is_active" class="form-check-label">启用</label>
</div>
