@extends('shop::layouts.pos')

@section('title', 'POS 收银')

@section('content')
    <header class="pos-topbar">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-cash-register"></i>
            <strong>{{ config('shop.name') }} · POS 收银</strong>
        </div>

        <div class="pos-topbar-meta" aria-label="当班信息">
            @if ($session)
                <span class="badge text-bg-secondary">终端：{{ $session->station?->name ?? '—' }}</span>
                <span class="badge text-bg-secondary">收银员：{{ $session->cashier?->name ?? '—' }}</span>
                <span class="text-muted small">开班：{{ optional($session->opened_at)->format('Y-m-d H:i') }}</span>
            @else
                <span class="badge text-bg-warning">尚未开班</span>
            @endif
        </div>

        <nav aria-label="收银台操作" class="d-flex gap-2">
            <button type="button" id="pos-save-btn" class="btn btn-outline-secondary btn-sm" title="快捷键 F3" @if (! $session) disabled @endif>
                <i class="fa-solid fa-bookmark"></i> 挂单 <span class="badge text-bg-light">F3</span>
            </button>
            <button type="button" id="pos-restore-btn" class="btn btn-outline-secondary btn-sm" title="快捷键 F4" @if (! $session) disabled @endif>
                <i class="fa-solid fa-folder-open"></i> 取单 <span class="badge text-bg-light">F4</span>
            </button>
            <button type="button" id="pos-refund-btn" class="btn btn-outline-secondary btn-sm" title="退款功能二期开放（P2-902），快捷键 F5" disabled>
                <i class="fa-solid fa-rotate-left"></i> 退款 <span class="badge text-bg-light">F5</span>
            </button>
            <button type="button" id="pos-close-shift-btn" class="btn btn-outline-secondary btn-sm" title="交班清点" @if (! $session) disabled @endif>
                <i class="fa-solid fa-hand"></i> 交班
            </button>
        </nav>
    </header>

    @if (! $session)
        <div class="alert alert-warning m-3 mb-0" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            当前账号没有未交班会话，请先在收银台开班后再进行收银。
        </div>
    @else
        <section id="pos-shift-summary" class="pos-shift-summary d-flex flex-wrap align-items-center gap-3 px-3 py-2 text-muted" aria-label="当班汇总">
            <span>笔数 <strong id="pos-shift-count" class="text-dark">0</strong></span>
            <span>当班销售额 <strong id="pos-shift-sales" class="text-dark">￥0.00</strong></span>
            <span>折扣 <strong id="pos-shift-discount" class="text-dark">￥0.00</strong></span>
            <span>应收现金 <strong id="pos-shift-cash" class="text-dark">￥0.00</strong></span>
            <span id="pos-shift-payments" class="small"></span>
        </section>
    @endif

    <main id="pos-main" class="pos-grid" tabindex="-1">
        {{-- 商品面板 --}}
        <aside class="pos-panel" aria-label="商品面板">
            <div class="pos-panel-search">
                <label for="pos-search" class="visually-hidden">扫码或输入商品编码、条码、名称</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-barcode"></i></span>
                    <input
                        id="pos-search"
                        name="search"
                        type="text"
                        class="form-control"
                        placeholder="扫码或输入编码 / 条码 / 名称"
                        autocomplete="off"
                        autofocus
                        @if (! $session) disabled @endif
                    >
                    <button type="button" id="pos-scan-btn" class="btn btn-outline-secondary"
                            title="摄像头扫码加购" aria-label="摄像头扫码加购"
                            @if (! $session) disabled @endif>
                        <i class="fa-solid fa-camera"></i>
                    </button>
                </div>
            </div>

            <div class="pos-catalog" role="list" aria-label="可售商品">
                @forelse ($products as $product)
                    <button
                        type="button"
                        class="pos-product"
                        role="listitem"
                        data-product-id="{{ $product->id }}"
                        data-code="{{ $product->code }}"
                        data-name="{{ $product->name }}"
                        data-price="{{ $product->list_price }}"
                        data-traceability="{{ $product->traceability }}"
                        @if (! $session) disabled @endif
                    >
                        <span class="pos-product-code">{{ $product->code }}</span>
                        <span class="pos-product-name">{{ $product->name }}</span>
                        <span class="pos-product-price">￥{{ number_format((float) $product->list_price, 2, '.', '') }}</span>
                    </button>
                @empty
                    <p class="text-muted text-center py-4">暂无可售商品</p>
                @endforelse
            </div>
        </aside>

        {{-- 购物车 --}}
        <section class="pos-panel" aria-label="购物车">
            <div class="pos-panel-title">
                <h2 class="h5 mb-0">购物车</h2>
                <span id="pos-cart-count" class="badge text-bg-secondary">0 件</span>
            </div>

            <div class="pos-cart">
                <p id="pos-cart-empty" class="text-muted text-center py-5" role="status">购物车为空，扫码或点击商品加购</p>
                <table class="table table-sm mb-0 d-none" id="pos-cart-table">
                    <caption class="visually-hidden">当前购物车明细</caption>
                    <thead>
                        <tr>
                            <th scope="col">商品</th>
                            <th scope="col" class="text-end">数量</th>
                            <th scope="col" class="text-end">单价</th>
                            <th scope="col" class="text-end">金额</th>
                            <th scope="col" class="text-end">操作</th>
                        </tr>
                    </thead>
                    <tbody id="pos-cart-rows"></tbody>
                </table>
            </div>
        </section>

        {{-- 支付面板 --}}
        <aside class="pos-panel" aria-label="支付面板">
            <div class="pos-panel-title">
                <h2 class="h5 mb-0">结算</h2>
            </div>

            <dl class="pos-summary">
                <div class="d-flex justify-content-between">
                    <dt>商品合计</dt>
                    <dd id="pos-subtotal">￥0.00</dd>
                </div>
                <div class="d-flex justify-content-between">
                    <dt>折扣</dt>
                    <dd id="pos-discount">-￥0.00</dd>
                </div>
                <div class="d-flex justify-content-between">
                    <dt>税额</dt>
                    <dd id="pos-tax">￥0.00</dd>
                </div>
                <div class="d-flex justify-content-between pos-summary-grand">
                    <dt>应收合计</dt>
                    <dd id="pos-grand-total">￥0.00</dd>
                </div>
            </dl>

            <div class="px-3 pb-2">
                <label for="pos-order-discount-rate" class="form-label small mb-1">整单折扣率（%）</label>
                <input
                    id="pos-order-discount-rate"
                    type="number"
                    class="form-control form-control-sm"
                    min="0" max="100" step="0.01" placeholder="0"
                >
            </div>

            <div class="pos-customer-pick px-3 pb-2">
                <label for="pos-customer-search" class="form-label small mb-1">客户（挂账必选）</label>
                <div class="input-group input-group-sm">
                    <input
                        id="pos-customer-search"
                        type="text"
                        class="form-control"
                        placeholder="输入客户编码或名称"
                        autocomplete="off"
                        @if (! $session) disabled @endif
                    >
                    <button type="button" id="pos-customer-clear" class="btn btn-outline-secondary" aria-label="清除客户">清除</button>
                </div>
                <div id="pos-customer-results" class="pos-customer-results d-none" role="listbox" aria-label="客户候选"></div>
                <div id="pos-customer-selected" class="small text-muted mt-1">未选择客户</div>
            </div>

            <div id="pos-payment-methods" class="pos-pay-methods" aria-label="支付方式">
                {{-- 支付方式按钮由前端按当班终端可用方式渲染（P1-509） --}}
            </div>

            <div id="pos-payment-rows" class="pos-payment-rows px-3" aria-live="polite"></div>

            <div class="px-3 mt-2">
                <button type="button" id="pos-payment-qr-btn" class="btn btn-outline-primary btn-sm w-100"
                        @if (! $session) disabled @endif>
                    <i class="fa-solid fa-qrcode me-1"></i>生成收款二维码
                </button>
            </div>

            <dl class="pos-summary px-3 mb-0" aria-label="支付小计">
                <div class="d-flex justify-content-between small">
                    <dt>已收</dt>
                    <dd id="pos-paid" class="mb-0">￥0.00</dd>
                </div>
                <div class="d-flex justify-content-between small">
                    <dt>剩余</dt>
                    <dd id="pos-due" class="mb-0">￥0.00</dd>
                </div>
                <div class="d-flex justify-content-between small">
                    <dt>找零</dt>
                    <dd id="pos-change" class="mb-0">￥0.00</dd>
                </div>
            </dl>

            <button
                type="button"
                id="pos-checkout-btn"
                class="btn btn-success btn-lg w-100 mt-3"
                title="快捷键 F2"
                @if (! $session) disabled @endif
            >
                <i class="fa-solid fa-circle-check"></i> 结账 <span class="badge text-bg-light">F2</span>
            </button>
            <p class="small text-muted mt-2 mb-0" aria-label="快捷键说明">
                回车 加购 · F2 结账 · F3 挂单 · F4 取单 · F5 退款（二期）
            </p>
            <p class="small mt-2 mb-0 d-none" id="pos-receipt-hint" aria-live="polite">
                <a href="#" id="pos-receipt-link" target="_blank" rel="noopener">
                    <i class="fa-solid fa-receipt me-1"></i><span id="pos-receipt-link-text">查看小票</span>
                </a>
            </p>
        </aside>
    </main>

    {{-- 批次/序列号选择 --}}
    <div id="pos-trace-dialog" class="pos-dialog d-none" role="dialog" aria-modal="true" aria-labelledby="pos-trace-title">
        <div class="pos-dialog-panel">
            <h2 id="pos-trace-title" class="h5">选择批次 / 序列号</h2>
            <p id="pos-trace-subject" class="small text-muted mb-2"></p>
            <label for="pos-trace-select" class="form-label small mb-1">可售批次 / 序列号</label>
            <select id="pos-trace-select" class="form-select form-select-sm"></select>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" id="pos-trace-cancel" class="btn btn-outline-secondary btn-sm">取消</button>
                <button type="button" id="pos-trace-confirm" class="btn btn-primary btn-sm">加入购物车</button>
            </div>
        </div>
    </div>

    {{-- 交班清点 --}}
    <div id="pos-shift-dialog" class="pos-dialog d-none" role="dialog" aria-modal="true" aria-labelledby="pos-shift-dialog-title">
        <div class="pos-dialog-panel">
            <h2 id="pos-shift-dialog-title" class="h5">交班清点</h2>
            <p class="small text-muted mb-2">按面额录入实点张数，系统自动合计并计算长短款。</p>
            <div id="pos-shift-denoms" class="row g-2"></div>
            <div class="d-flex justify-content-between small mt-2">
                <span>实点合计 <strong id="pos-shift-counted">￥0.00</strong></span>
                <span>应收现金 <strong id="pos-shift-expected">￥0.00</strong></span>
            </div>
            <label for="pos-shift-note" class="form-label small mt-2 mb-1">交班备注</label>
            <textarea id="pos-shift-note" class="form-control form-control-sm" rows="2" maxlength="255"></textarea>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button type="button" id="pos-shift-cancel" class="btn btn-outline-secondary btn-sm">取消</button>
                <button type="button" id="pos-shift-confirm" class="btn btn-warning btn-sm">确认交班</button>
            </div>
        </div>
    </div>
    {{-- 收款二维码 --}}
    <div id="pos-qr-dialog" class="pos-dialog d-none" role="dialog" aria-modal="true" aria-labelledby="pos-qr-title">
        <div class="pos-dialog-panel text-center">
            <h2 id="pos-qr-title" class="h5">收款二维码</h2>
            <p class="small text-muted mb-2">顾客扫码后，请核对到账金额再完成收款。</p>
            <div id="pos-qr-canvas" class="d-inline-block p-2 bg-white border rounded"></div>
            <p class="h5 mt-2 mb-1" id="pos-qr-amount">￥0.00</p>
            <p class="small text-muted mb-0" id="pos-qr-token"></p>
            <p class="small text-muted mb-0" id="pos-qr-expire"></p>
            <div class="d-flex justify-content-center gap-2 mt-3">
                <button type="button" id="pos-qr-refresh" class="btn btn-outline-secondary btn-sm">刷新二维码</button>
                <button type="button" id="pos-qr-close" class="btn btn-primary btn-sm">关闭</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @php
        $canAdjustPrice = auth()->user()->hasPermission('pos.price.adjust')
            || auth()->user()->roles()->where('code', 'super-admin')->exists();
    @endphp
    <script>
        window.POS = {
            searchUrl: "{{ route('shop.pos.search') }}",
            cartAddUrl: "{{ route('shop.pos.cart.add') }}",
            cartQtyUrl: "{{ route('shop.pos.cart.qty') }}",
            cartPriceUrl: "{{ route('shop.pos.cart.price') }}",
            cartDiscountUrl: "{{ route('shop.pos.cart.discount') }}",
            cartOrderDiscountUrl: "{{ route('shop.pos.cart.order-discount') }}",
            cartRemoveUrl: "{{ route('shop.pos.cart.remove') }}",
            paymentMethodsUrl: "{{ route('shop.pos.payment-methods') }}",
            paymentQrUrl: "{{ route('shop.pos.payment-qr') }}",
            lotsUrl: "{{ route('shop.pos.trace.lots') }}",
            serialsUrl: "{{ route('shop.pos.trace.serials') }}",
            customersUrl: "{{ route('shop.pos.customers') }}",
            closeShiftUrl: "{{ route('shop.pos.session.close') }}",
            summaryUrl: "{{ route('shop.pos.session.summary') }}",
            checkoutUrl: "{{ route('shop.pos.checkout') }}",
            receiptUrl: "{{ route('shop.pos.orders.receipt', ['posOrder' => 0]) }}",
            savedIndexUrl: "{{ route('shop.pos.saved.index') }}",
            savedSaveUrl: "{{ route('shop.pos.saved.save') }}",
            savedRestoreUrl: "{{ route('shop.pos.saved.restore', ['id' => 0]) }}",
            savedDeleteUrl: "{{ route('shop.pos.saved.delete', ['id' => 0]) }}",
            canAdjustPrice: @json($canAdjustPrice),
            autoPrintReceipt: @json($autoPrintReceipt ?? false)
        };
    </script>
    <script src="{{ asset('assets/shop/js/pos.js') }}"></script>
@endpush
