@extends('shop::layouts.app')

@section('title', '新建报废单')
@section('heading', '新建报废单')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">报废商品将从库存出库，并按标准成本结转损失</p>
        <a href="{{ route('shop.scraps.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i>返回列表
        </a>
    </div>

    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <form method="post" action="{{ route('shop.scraps.store') }}" aria-label="新建报废单表单">
        @csrf

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label for="warehouse_id" class="form-label">仓库 <span aria-hidden="true">*</span></label>
                <select id="warehouse_id" name="warehouse_id" class="form-select @error('warehouse_id') is-invalid @enderror" required>
                    <option value="">请选择仓库</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(old('warehouse_id') == $warehouse->id)>
                            {{ $warehouse->code }} · {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
                @error('warehouse_id')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror>
            </div>
            <div class="col-md-4">
                <label for="scrap_date" class="form-label">报废日期</label>
                <input type="date" id="scrap_date" name="scrap_date"
                       value="{{ old('scrap_date', date('Y-m-d')) }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label for="loss_type" class="form-label">损失类型</label>
                <select id="loss_type" name="loss_type" class="form-select">
                    <option value="expense" @selected(old('loss_type', 'expense') === 'expense')>管理费用</option>
                    <option value="other" @selected(old('loss_type') === 'other')>营业外支出</option>
                </select>
            </div>
        </div>

        <div class="card p-0 mb-3">
            <div class="table-wrapper">
                <table class="table table-hover mb-0">
                    <caption class="visually-hidden">报废明细行</caption>
                    <thead>
                        <tr>
                            <th scope="col">商品 <span aria-hidden="true">*</span></th>
                            <th scope="col">库位</th>
                            <th scope="col">批次号</th>
                            <th scope="col" class="text-end">报废数量 <span aria-hidden="true">*</span></th>
                            <th scope="col">行原因</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $index = 0; @endphp
                        <tr>
                            <td>
                                <select name="lines[{{ $index }}][product_id]" class="form-select form-select-sm @error("lines.$index.product_id") is-invalid @enderror" required>
                                    <option value="">请选择商品</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}" @selected(old("lines.$index.product_id") == $product->id)>
                                            {{ $product->code }} · {{ $product->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="lines[{{ $index }}][location_id]" class="form-select form-select-sm">
                                    <option value="">默认库位</option>
                                    @foreach ($locations as $location)
                                        <option value="{{ $location->id }}" @selected(old("lines.$index.location_id") == $location->id)>
                                            {{ $location->code }} · {{ $location->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" maxlength="50" name="lines[{{ $index }}][lot_no]"
                                       value="{{ old("lines.$index.lot_no") }}" class="form-control form-control-sm">
                            </td>
                            <td class="text-end">
                                <input type="number" step="0.0001" min="0.0001" name="lines[{{ $index }}][qty]"
                                       value="{{ old("lines.$index.qty") }}" class="form-control form-control-sm text-end"
                                       style="inline-size:6rem;" required>
                            </td>
                            <td>
                                <input type="text" maxlength="255" name="lines[{{ $index }}][reason]"
                                       value="{{ old("lines.$index.reason") }}" class="form-control form-control-sm">
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mb-3">
            <label for="reason" class="form-label">报废原因（整单） <span aria-hidden="true">*</span></label>
            <input type="text" id="reason" name="reason" maxlength="255" value="{{ old('reason') }}"
                   class="form-control @error('reason') is-invalid @enderror" required>
            @error('reason')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror>
        </div>

        <div class="mb-3">
            <label for="note" class="form-label">备注</label>
            <textarea id="note" name="note" rows="2" maxlength="255" class="form-control">{{ old('note') }}</textarea>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存报废草稿</button>
            <a href="{{ route('shop.scraps.index') }}" class="btn btn-outline-secondary">取消</a>
        </div>
    </form>
@endsection
