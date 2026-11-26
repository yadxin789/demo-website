@csrf
@isset($method)
    @method($method)
@endisset

<div class="mb-3">
    <label for="name" class="form-label">名称 <span aria-hidden="true">*</span></label>
    <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
           value="{{ old('name', $tax?->name ?? '') }}" required maxlength="50">
    @error('name')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="code" class="form-label">编码 <span aria-hidden="true">*</span></label>
    <input type="text" id="code" name="code" class="form-control @error('code') is-invalid @enderror"
           value="{{ old('code', $tax?->code ?? '') }}" required maxlength="20"
           autocomplete="off" autocapitalize="none" spellcheck="false">
    @error('code')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
</div>

<div class="row mb-3">
    <div class="col-sm-6">
        <label for="rate" class="form-label">税率（%） <span aria-hidden="true">*</span></label>
        <input type="number" id="rate" name="rate" class="form-control @error('rate') is-invalid @enderror"
               value="{{ old('rate', $tax?->rate ?? 0) }}" min="0" max="100" step="0.01" inputmode="decimal" required>
        @error('rate')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
    <div class="col-sm-6">
        <label for="type" class="form-label">计税方式 <span aria-hidden="true">*</span></label>
        <select id="type" name="type" class="form-select @error('type') is-invalid @enderror" required>
            @php($type = old('type', $tax?->type ?? 'exclusive'))
            <option value="exclusive" @selected($type === 'exclusive')>价外税（不含税）</option>
            <option value="inclusive" @selected($type === 'inclusive')>价内税（含税）</option>
        </select>
        @error('type')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mb-3">
    <label for="account_id" class="form-label">税费科目 ID</label>
    <input type="number" id="account_id" name="account_id" class="form-control"
           value="{{ old('account_id', $tax?->account_id ?? '') }}" min="1" inputmode="numeric"
           placeholder="科目表建成后回填">
    <p class="form-text">可选；关联「应交税费」科目，会计分录据此自动生成。</p>
</div>

<div class="form-check mb-3">
    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" value="1"
           @checked(old('is_active', $tax?->is_active ?? true))>
    <label for="is_active" class="form-check-label">启用该税率</label>
</div>
