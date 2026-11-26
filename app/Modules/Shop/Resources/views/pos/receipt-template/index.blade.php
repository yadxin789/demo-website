@extends('shop::layouts.app')

@section('title', '小票模板')
@section('heading', '小票模板')

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card">
                <div class="card-body">
                    <h2 class="h6 mb-3">模板内容</h2>
                    <p class="text-muted small">
                        保存后 ESC/POS 打印与浏览器备选小票同时生效；页脚支持变量，留空则使用内置文案。
                    </p>

                    <form method="post" action="{{ route('shop.pos.receipt-template.update') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="tpl-store-name" class="form-label">抬头（店名）<span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" id="tpl-store-name" name="store_name" maxlength="80"
                                   value="{{ old('store_name', $template->store_name) }}"
                                   class="form-control @error('store_name') is-invalid @enderror" required>
                            @error('store_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-7">
                                <label for="tpl-address" class="form-label">地址</label>
                                <input type="text" id="tpl-address" name="address" maxlength="160"
                                       value="{{ old('address', $template->address) }}"
                                       class="form-control @error('address') is-invalid @enderror">
                                @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 col-md-5">
                                <label for="tpl-phone" class="form-label">电话</label>
                                <input type="text" id="tpl-phone" name="phone" maxlength="40"
                                       value="{{ old('phone', $template->phone) }}"
                                       class="form-control @error('phone') is-invalid @enderror">
                                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="tpl-footer" class="form-label">页脚文案</label>
                            <textarea id="tpl-footer" name="footer" rows="2" maxlength="160"
                                      class="form-control @error('footer') is-invalid @enderror"
                                      aria-describedby="tpl-footer-help">{{ old('footer', $template->footer) }}</textarea>
                            @error('footer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div id="tpl-footer-help" class="form-text">
                                可用变量：<code>{store_name}</code> <code>{order_code}</code> <code>{order_date}</code>
                                <code>{cashier}</code> <code>{customer}</code> <code>{total}</code> <code>{item_count}</code>
                            </div>
                        </div>

                        <fieldset class="mb-3">
                            <legend class="form-label h6">打印项</legend>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="tpl-show-discount"
                                       name="show_discount" value="1" @checked(old('show_discount', $template->show_discount))>
                                <label class="form-check-label" for="tpl-show-discount">打印折扣行</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="tpl-show-cashier"
                                       name="show_cashier" value="1" @checked(old('show_cashier', $template->show_cashier))>
                                <label class="form-check-label" for="tpl-show-cashier">打印收银员</label>
                            </div>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="tpl-show-barcode"
                                       name="show_barcode" value="1" @checked(old('show_barcode', $template->show_barcode))>
                                <label class="form-check-label" for="tpl-show-barcode">打印单号条码（CODE39，仅小票打印机）</label>
                            </div>
                        </fieldset>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>保存模板</button>
                            <a href="{{ route('shop.pos.stations.index') }}" class="btn btn-outline-secondary">收银台配置</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-2">32 列预览（示例订单）</h2>
                    <p class="text-muted small mb-2">预览按当前已保存配置生成，修改后保存即可刷新。</p>
                    <pre class="border rounded p-3 bg-body-tertiary small mb-0" style="white-space: pre; overflow-x: auto;" aria-label="小票预览">{{ $preview }}</pre>
                </div>
            </div>
        </div>
    </div>
@endsection
