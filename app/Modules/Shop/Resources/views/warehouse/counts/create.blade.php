@extends('shop::layouts.app')

@section('title', '新建盘点单')
@section('heading', '新建盘点单')

@section('content')
    <div class="card" style="max-inline-size:40rem;">
        <form method="post" action="{{ route('shop.counts.store') }}" aria-label="新建盘点单表单">
            @csrf

            <div class="mb-3">
                <label for="warehouse_id" class="form-label">仓库 <span aria-hidden="true">*</span></label>
                <select id="warehouse_id" name="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                    <option value="">请选择仓库</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>
                            {{ $warehouse->code }} · {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
                @error('warehouse_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="location_id" class="form-label">库位</label>
                <select id="location_id" name="location_id" class="form-select @error('location_id') is-invalid @enderror">
                    <option value="">全部库位</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id') == $location->id)>
                            {{ $location->code }} · {{ $location->name }}
                        </option>
                    @endforeach
                </select>
                @error('location_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="count_date" class="form-label">盘点日期</label>
                <input type="date" id="count_date" name="count_date" value="{{ old('count_date', date('Y-m-d')) }}" class="form-control @error('count_date') is-invalid @enderror">
                @error('count_date')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label for="note" class="form-label">备注</label>
                <textarea id="note" name="note" rows="2" maxlength="255" class="form-control @error('note') is-invalid @enderror">{{ old('note') }}</textarea>
                @error('note')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>生成盘点单</button>
                <a href="{{ route('shop.counts.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
