@extends('shop::layouts.app')

@section('title', $station->exists ? '编辑收银台' : '新建收银台')
@section('heading', $station->exists ? '编辑收银台' : '新建收银台')

@section('content')
    @php
        $isUpdate = $station->exists;
        $action = $isUpdate ? route('shop.pos.stations.update', $station) : route('shop.pos.stations.store');
        $selectedMethods = old('payment_method_ids', $station->payment_method_ids ?? []);
    @endphp

    <div class="card" style="max-inline-size:44rem;">
        <form method="post" action="{{ $action }}" aria-label="收银台配置表单">
            @csrf
            @if ($isUpdate)
                @method('PATCH')
            @endif

            <div class="mb-3">
                <label for="code" class="form-label">终端编码 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="code" name="code" value="{{ old('code', $station->code) }}" required>
            </div>

            <div class="mb-3">
                <label for="name" class="form-label">终端名称 <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="name" name="name" value="{{ old('name', $station->name) }}" required>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label for="warehouse_id" class="form-label">默认仓库 <span class="text-danger">*</span></label>
                    <select class="form-select" id="warehouse_id" name="warehouse_id" required>
                        @foreach ($warehouses as $warehouse)
                            <option value="{{ $warehouse->id }}" @selected(old('warehouse_id', $station->warehouse_id) == $warehouse->id)>{{ $warehouse->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col">
                    <label for="default_location_id" class="form-label">默认库位</label>
                    <select class="form-select" id="default_location_id" name="default_location_id">
                        <option value="">— 不指定 —</option>
                        @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected(old('default_location_id', $station->default_location_id) == $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <fieldset class="mb-3">
                <legend class="form-label">可用支付方式（不选则全部可用）</legend>
                @foreach ($methods as $method)
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="method-{{ $method->id }}" name="payment_method_ids[]" value="{{ $method->id }}"
                            @checked(in_array($method->id, $selectedMethods, true))>
                        <label class="form-check-label" for="method-{{ $method->id }}">{{ $method->name }}（{{ $method->type }}）</label>
                    </div>
                @endforeach
            </fieldset>

            <hr>

            <div class="row mb-3">
                <div class="col">
                    <label for="printer_driver" class="form-label">打印驱动</label>
                    <select class="form-select" id="printer_driver" name="printer_driver">
                        @foreach (['escpos' => 'ESC/POS', 'browser' => '浏览器'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('printer_driver', $station->printer_driver ?? 'escpos') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col">
                    <label for="paper_width" class="form-label">纸宽</label>
                    <select class="form-select" id="paper_width" name="paper_width">
                        @foreach ([58 => '58mm', 80 => '80mm'] as $value => $label)
                            <option value="{{ $value }}" @selected((int) old('paper_width', $station->paper_width ?? 58) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label for="printer_address" class="form-label">打印机地址（IP / 串口 / USB）</label>
                    <input type="text" class="form-control" id="printer_address" name="printer_address" value="{{ old('printer_address', $station->printer_address) }}" placeholder="如 192.168.1.100 或 COM1">
                </div>
                <div class="col">
                    <label for="printer_port" class="form-label">端口</label>
                    <input type="text" class="form-control" id="printer_port" name="printer_port" value="{{ old('printer_port', $station->printer_port) }}" placeholder="9100">
                </div>
            </div>

            <div class="row mb-3">
                <div class="col">
                    <label for="encoding" class="form-label">字符编码</label>
                    <select class="form-select" id="encoding" name="encoding">
                        @foreach (['gbk' => 'GBK', 'gb2312' => 'GB2312', 'utf-8' => 'UTF-8'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('encoding', $station->encoding ?? 'gbk') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col">
                    <label for="cash_drawer_pin" class="form-label">钱箱脉冲引脚</label>
                    <input type="text" class="form-control" id="cash_drawer_pin" name="cash_drawer_pin" value="{{ old('cash_drawer_pin', $station->cash_drawer_pin) }}" placeholder="0">
                </div>
            </div>

            <div class="mb-3 form-check">
                <input class="form-check-input" type="checkbox" id="auto_print_receipt" name="auto_print_receipt" value="1"
                    @checked(old('auto_print_receipt', $station->auto_print_receipt ?? false))>
                <label class="form-check-label" for="auto_print_receipt">结账后自动调起浏览器打印对话框</label>
                <div class="small text-muted">关闭时只自动打开小票页，打印需手动点击「打印小票」。</div>
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">状态</label>
                <select class="form-select" id="status" name="status">
                    @foreach (['active' => '启用', 'disabled' => '停用'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $station->status ?? 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check me-1"></i>保存配置</button>
                <a href="{{ route('shop.pos.stations.index') }}" class="btn btn-outline-secondary">取消</a>
            </div>
        </form>
    </div>
@endsection
