/* 百货零售 ERP — POS 收银前台脚本（离线，原生 JS，无第三方依赖）
 * 购物车状态在浏览器内存维护；取价、税额、折扣与留痕以后端 CartService 为准。
 * 结账走支付面板（多笔支付 / 挂账客户 / 找零预览），批次与序列号商品先选号再加购。
 */
(function () {
    'use strict';

    var cart = [];
    var orderDiscountRate = 0;
    var lastGrand = 0;
    var selectedPayments = [];
    var selectedCustomer = null;
    var paymentMethods = [];

    var searchInput = document.getElementById('pos-search');
    var catalog = document.querySelector('.pos-catalog');
    var emptyState = document.getElementById('pos-cart-empty');
    var cartTable = document.getElementById('pos-cart-table');
    var cartRows = document.getElementById('pos-cart-rows');
    var cartCount = document.getElementById('pos-cart-count');
    var subtotalEl = document.getElementById('pos-subtotal');
    var discountEl = document.getElementById('pos-discount');
    var taxEl = document.getElementById('pos-tax');
    var grandEl = document.getElementById('pos-grand-total');
    var orderDiscountInput = document.getElementById('pos-order-discount-rate');
    var saveBtn = document.getElementById('pos-save-btn');
    var restoreBtn = document.getElementById('pos-restore-btn');
    var paymentMethodsEl = document.getElementById('pos-payment-methods');
    var paymentRowsEl = document.getElementById('pos-payment-rows');
    var checkoutBtn = document.getElementById('pos-checkout-btn');
    var paymentQrBtn = document.getElementById('pos-payment-qr-btn');
    var qrDialog = document.getElementById('pos-qr-dialog');
    var qrCanvas = document.getElementById('pos-qr-canvas');
    var qrAmountEl = document.getElementById('pos-qr-amount');
    var qrTokenEl = document.getElementById('pos-qr-token');
    var qrExpireEl = document.getElementById('pos-qr-expire');
    var statusEl = document.getElementById('pos-cart-empty');
    var paidEl = document.getElementById('pos-paid');
    var dueEl = document.getElementById('pos-due');
    var changeEl = document.getElementById('pos-change');
    var customerSearch = document.getElementById('pos-customer-search');
    var customerClear = document.getElementById('pos-customer-clear');
    var customerResults = document.getElementById('pos-customer-results');
    var customerSelectedEl = document.getElementById('pos-customer-selected');
    var closeShiftBtn = document.getElementById('pos-close-shift-btn');
    var receiptHintEl = document.getElementById('pos-receipt-hint');
    var receiptLinkEl = document.getElementById('pos-receipt-link');
    var receiptLinkTextEl = document.getElementById('pos-receipt-link-text');
    var traceDialog = document.getElementById('pos-trace-dialog');
    var traceSubject = document.getElementById('pos-trace-subject');
    var traceSelect = document.getElementById('pos-trace-select');
    var traceConfirm = document.getElementById('pos-trace-confirm');
    var traceCancel = document.getElementById('pos-trace-cancel');
    var shiftDialog = document.getElementById('pos-shift-dialog');
    var shiftDenomsEl = document.getElementById('pos-shift-denoms');
    var shiftCountedEl = document.getElementById('pos-shift-counted');
    var shiftExpectedEl = document.getElementById('pos-shift-expected');
    var shiftNote = document.getElementById('pos-shift-note');
    var shiftConfirm = document.getElementById('pos-shift-confirm');
    var shiftCancel = document.getElementById('pos-shift-cancel');

    var PAYMENT_LABELS = {
        cash: '现金', bank: '银行', card: '银行卡',
        electronic: '电子', coupon: '储值券', credit: '挂账', unknown: '其他'
    };

    function money(value) {
        return '￥' + Number(value || 0).toFixed(2);
    }

    function round2(value) {
        return Math.round(Number(value || 0) * 100) / 100;
    }

    function lineKey(line) {
        return String(line.product_id) + '|' + (line.lot_no || '') + '|' + (line.serial_no || '');
    }

    function findLine(productId, lotNo, serialNo) {
        var key = String(productId) + '|' + (lotNo || '') + '|' + (serialNo || '');
        return cart.find(function (line) { return lineKey(line) === key; });
    }

    function applyLine(serverLine) {
        var existing = findLine(serverLine.product_id, serverLine.lot_no, serverLine.serial_no);
        if (existing && !serverLine.requires_serial) {
            existing.qty = Number(existing.qty) + Number(serverLine.qty);
            existing.subtotal = (Number(existing.qty) * Number(existing.unit_price)).toFixed(4);
            existing.tax_amount = (Number(existing.subtotal) * Number(existing.tax_rate) / 100).toFixed(4);
        } else {
            cart.push(serverLine);
        }
        render();
    }

    function render() {
        var subtotal = 0, tax = 0, qtyTotal = 0;

        cartRows.innerHTML = '';

        cart.forEach(function (line, index) {
            var subtotalLine = Number(line.subtotal || 0);
            var taxLine = Number(line.tax_amount || 0);
            subtotal += subtotalLine;
            tax += taxLine;
            qtyTotal += Number(line.qty);

            var note = '';
            if (line.requires_lot) { note += '<span class="badge text-bg-info ms-1">批次</span>'; }
            if (line.requires_serial) { note += '<span class="badge text-bg-info ms-1">序列号</span>'; }
            if (line.below_cost) { note += '<span class="badge text-bg-warning ms-1">低于成本</span>'; }
            if (line.price_adjusted) { note += '<span class="badge text-bg-secondary ms-1">改价</span>'; }

            var trace = '';
            if (line.lot_no) { trace += '<div class="small text-muted">批次：' + escapeHtml(line.lot_no) + '</div>'; }
            if (line.serial_no) { trace += '<div class="small text-muted">序列号：' + escapeHtml(line.serial_no) + '</div>'; }

            var canAdjust = window.POS.canAdjustPrice;
            var qty = Number(line.qty);
            var serial = Boolean(line.requires_serial);

            var tr = document.createElement('tr');
            tr.dataset.index = index;
            tr.dataset.key = lineKey(line);
            tr.innerHTML =
                '<td>' + escapeHtml(line.name) + note + trace +
                '<div class="small text-muted">' + escapeHtml(line.code) + '</div></td>' +
                '<td class="text-end">' + qty + '</td>' +
                '<td class="text-end">' + money(line.unit_price) + '</td>' +
                '<td class="text-end">' + money(subtotalLine) + '</td>' +
                '<td class="text-end"><div class="d-flex gap-1 justify-content-end align-items-center">' +
                '<button type="button" data-action="dec" class="btn btn-sm btn-outline-secondary" ' + (qty <= 1 || serial ? 'disabled' : '') + ' aria-label="减少数量">−</button>' +
                '<button type="button" data-action="inc" class="btn btn-sm btn-outline-secondary" ' + (serial ? 'disabled' : '') + ' aria-label="增加数量">＋</button>' +
                '<button type="button" data-action="price" class="btn btn-sm btn-outline-primary" ' + (canAdjust ? '' : 'disabled') + ' aria-label="改价" title="改价"><i class="fa-solid fa-pen"></i></button>' +
                '<button type="button" data-action="discount" class="btn btn-sm btn-outline-primary" aria-label="行折扣" title="行折扣"><i class="fa-solid fa-percent"></i></button>' +
                '<button type="button" data-action="remove" class="btn btn-sm btn-outline-danger" aria-label="删除该行" title="删除"><i class="fa-solid fa-trash"></i></button>' +
                '</div></td>';
            cartRows.appendChild(tr);
        });

        var empty = cart.length === 0;
        if (statusEl) {
            statusEl.hidden = !empty;
            statusEl.textContent = empty ? '购物车为空，扫码或点击商品加购' : '';
        }
        if (cartTable) { cartTable.classList.toggle('d-none', empty); }
        if (cartCount) { cartCount.textContent = qtyTotal + ' 件'; }

        lastGrand = round2(subtotal + tax);
        if (subtotalEl) { subtotalEl.textContent = money(subtotal); }
        if (discountEl) { discountEl.textContent = '-' + money(Number(lineDiscountTotal())); }
        if (taxEl) { taxEl.textContent = money(tax); }
        if (grandEl) { grandEl.textContent = money(lastGrand); }

        if (orderDiscountRate > 0) { refreshOrderTotals(); }
        renderPaymentSummary();
    }

    function applyOrderTotals(t) {
        var totalDiscount = Number(t.line_discount_amount || 0) + Number(t.order_discount_amount || 0);
        lastGrand = Number(t.grand_total || 0);
        if (discountEl) { discountEl.textContent = '-' + money(totalDiscount); }
        if (grandEl) { grandEl.textContent = money(lastGrand); }
        renderPaymentSummary();
    }

    async function refreshOrderTotals() {
        if (!cart.length) { return; }
        try {
            var res = await window.shopFetch(window.POS.cartOrderDiscountUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lines: cart, discount_rate: orderDiscountRate })
            });
            if (!res.ok) { throw new Error('折扣计算失败'); }
            var data = await res.json();
            applyOrderTotals(data.totals);
        } catch (err) {
            if (statusEl) { statusEl.textContent = err.message || '折扣计算失败'; }
        }
    }

    function lineDiscountTotal() {
        return cart.reduce(function (sum, line) {
            var gross = Number(line.qty) * Number(line.unit_price);
            return sum + (gross - Number(line.subtotal || 0));
        }, 0);
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text == null ? '' : text);
        return div.innerHTML;
    }

    function paidTotal() {
        return round2(selectedPayments.reduce(function (sum, p) { return sum + Number(p.amount || 0); }, 0));
    }

    function setStatus(message) {
        if (statusEl) { statusEl.textContent = message; }
    }

    // ===== 结账后自动弹出浏览器小票 =====

    function receiptUrlFor(orderId) {
        var base = window.POS.receiptUrl || '';
        var url = base.replace(/\/0\/receipt(\?|$)/, '/' + encodeURIComponent(orderId) + '/receipt$1');
        if (window.POS.autoPrintReceipt) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + 'auto=1';
        }
        return url;
    }

    // 在点击手势内先开空白窗口，异步结账成功后再导航，避免被浏览器当作弹窗拦截。
    function preopenReceiptWindow() {
        try {
            var win = window.open('', 'pos-receipt', 'width=480,height=760,menubar=no,toolbar=no,location=no,status=no');
            if (win && !win.closed) {
                try {
                    win.document.write('<!doctype html><title>小票打印中…</title><body style="font-family:system-ui;padding:24px;color:#555">正在生成小票…</body>');
                    win.document.close();
                } catch (e) { /* 空白页可能尚未就绪，忽略 */ }
                return win;
            }
        } catch (e) { /* 被拦截时走页面兜底链接 */ }
        return null;
    }

    function showReceiptLink(order) {
        if (!receiptLinkEl || !order) { return; }
        var url = receiptUrlFor(order.id);
        receiptLinkEl.setAttribute('href', url);
        if (receiptLinkTextEl) {
            receiptLinkTextEl.textContent = '查看小票 ' + order.code;
        }
        if (receiptHintEl) { receiptHintEl.classList.remove('d-none'); }
    }

    async function readError(res, fallback) {
        try {
            var data = await res.json();
            if (data && data.message) { return data.message; }
        } catch (err) { /* 非 JSON 响应 */ }
        return fallback || ('请求失败：' + res.status);
    }

    async function callEndpoint(url, payload, line) {
        var body = Object.assign({ lines: cart }, payload);
        if (line) {
            body.lot_no = line.lot_no || null;
            body.serial_no = line.serial_no || null;
        }
        var res = await window.shopFetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        if (res.status === 403) { throw new Error('无权限操作'); }
        if (!res.ok) { throw new Error(await readError(res, '操作失败')); }
        var data = await res.json();
        cart = data.cart;
        render();
    }

    async function fetchLine(productId, qty, lotNo, serialNo) {
        var res = await window.shopFetch(window.POS.cartAddUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId,
                qty: qty || 1,
                lot_no: lotNo || null,
                serial_no: serialNo || null
            })
        });
        if (!res.ok) { throw new Error(await readError(res, '加购失败')); }
        var data = await res.json();
        return data.line;
    }

    // ===== 批次 / 序列号选择 =====

    function openTracePicker(product, qty) {
        return new Promise(function (resolve) {
            var kind = product.traceability === 'serial' ? 'serial' : 'lot';
            var url = kind === 'serial' ? window.POS.serialsUrl : window.POS.lotsUrl;

            traceSubject.textContent = product.name + '（' + product.code + '）' +
                (kind === 'serial' ? '，请选择在库序列号' : '，请选择出货批次');
            traceSelect.innerHTML = '<option value="">加载中…</option>';
            traceConfirm.disabled = true;
            traceDialog.classList.remove('d-none');
            traceSelect.focus();

            function close(result) {
                traceDialog.classList.add('d-none');
                resolve(result);
            }

            function cleanup() {
                traceSelect.onchange = null;
                traceConfirm.onclick = null;
                traceCancel.onclick = null;
            }

            traceCancel.onclick = function () { cleanup(); close(null); };
            traceConfirm.onclick = function () {
                if (!traceSelect.value) { return; }
                var result = kind === 'serial'
                    ? { serial_no: traceSelect.value }
                    : { lot_no: traceSelect.value };
                cleanup();
                close(result);
            };

            window.shopFetch(url + '?product_id=' + encodeURIComponent(product.id))
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    var items = kind === 'serial' ? (data.serials || []) : (data.lots || []);
                    if (!items.length) {
                        traceSelect.innerHTML = '<option value="">无可售' + (kind === 'serial' ? '序列号' : '批次') + '</option>';
                        traceConfirm.disabled = true;
                        return;
                    }

                    traceSelect.innerHTML = '';
                    items.forEach(function (item) {
                        var option = document.createElement('option');
                        if (kind === 'serial') {
                            option.value = item.serial_no;
                            option.textContent = item.serial_no;
                        } else {
                            option.value = item.lot_no;
                            option.textContent = item.lot_no + '（可用 ' + item.available + '）';
                        }
                        traceSelect.appendChild(option);
                    });
                    traceConfirm.disabled = false;
                })
                .catch(function () {
                    traceSelect.innerHTML = '<option value="">加载失败，请重试</option>';
                    traceConfirm.disabled = true;
                });
        });
    }

    async function addByProduct(productId, traceability, name, preset) {
        try {
            var qty = 1;
            var lotNo = null, serialNo = null;

            if (preset && preset.lot_no) { lotNo = preset.lot_no; }
            if (preset && preset.serial_no) { serialNo = preset.serial_no; }

            if ((traceability === 'lot' || traceability === 'serial') && !lotNo && !serialNo) {
                var product = { id: productId, code: '', name: name || '' };
                var pick = await openTracePicker(product, qty);
                if (!pick) { return; }
                if (pick.lot_no) { lotNo = pick.lot_no; }
                if (pick.serial_no) {
                    serialNo = pick.serial_no;
                    qty = 1;
                    if (findLine(productId, null, serialNo)) {
                        setStatus('序列号 ' + serialNo + ' 已在购物车，请勿重复加购');
                        return;
                    }
                }
            }

            if (traceability === 'serial' && serialNo) {
                qty = 1;
                if (findLine(productId, null, serialNo)) {
                    setStatus('序列号 ' + serialNo + ' 已在购物车，请勿重复加购');
                    return;
                }
            }

            var line = await fetchLine(productId, qty, lotNo, serialNo);
            applyLine(line);
            setStatus('');
        } catch (err) {
            setStatus(err.message || '加购失败，请重试');
        }
    }

    async function renderCatalog(query) {
        var url = window.POS.searchUrl + '?q=' + encodeURIComponent(query || '');
        var res = await window.shopFetch(url);
        var data = await res.json();
        var products = data.products || [];

        catalog.innerHTML = '';
        if (products.length === 0) {
            var p = document.createElement('p');
            p.className = 'text-muted text-center py-4';
            p.textContent = '未找到匹配商品';
            catalog.appendChild(p);
            return;
        }

        products.forEach(function (product) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-product';
            btn.dataset.productId = product.id;
            btn.dataset.code = product.code;
            btn.dataset.name = product.name;
            btn.dataset.price = product.list_price;
            btn.dataset.traceability = product.traceability;
            btn.innerHTML =
                '<span class="pos-product-code">' + escapeHtml(product.code) + '</span>' +
                '<span class="pos-product-name">' + escapeHtml(product.name) + '</span>' +
                '<span class="pos-product-price">' + money(product.list_price) + '</span>';
            catalog.appendChild(btn);
        });
    }

    async function matchAndAdd() {
        var query = searchInput.value.trim();
        if (!query) { return; }

        // 先按统一扫码规范解析（支持商品二维码、批次码、序列号码）
        if (window.ErpScan) {
            try {
                var scanned = await window.ErpScan.resolve(query);
                if (scanned.type === 'product') {
                    await addByProduct(scanned.data.id, scanned.data.traceability, scanned.data.name);
                    searchInput.value = '';
                    searchInput.focus();
                    return;
                }
                if (scanned.type === 'lot') {
                    await addByProduct(scanned.data.product_id, 'lot', scanned.data.product_name, { lot_no: scanned.data.lot_no });
                    searchInput.value = '';
                    searchInput.focus();
                    return;
                }
                if (scanned.type === 'serial') {
                    await addByProduct(scanned.data.product_id, 'serial', scanned.data.product_name, { serial_no: scanned.data.serial_no });
                    searchInput.value = '';
                    searchInput.focus();
                    return;
                }
            } catch (ignored) {
                // 非扫码内容，继续按关键字检索
            }
        }

        var res = await window.shopFetch(window.POS.searchUrl + '?q=' + encodeURIComponent(query));
        var data = await res.json();
        var products = data.products || [];
        if (products.length === 0) {
            setStatus('未找到商品：' + query);
            return;
        }

        await addByProduct(products[0].id, products[0].traceability, products[0].name);
        searchInput.value = '';
        searchInput.focus();
    }

    async function handleRowAction(action, index) {
        var line = cart[index];
        if (!line) { return; }

        try {
            if (action === 'inc') {
                await callEndpoint(window.POS.cartQtyUrl, { product_id: line.product_id, qty: Number(line.qty) + 1 }, line);
            } else if (action === 'dec') {
                await callEndpoint(window.POS.cartQtyUrl, { product_id: line.product_id, qty: Number(line.qty) - 1 }, line);
            } else if (action === 'price') {
                var price = prompt('输入新单价（原价 ' + line.original_price + '）', line.unit_price);
                if (price === null) { return; }
                await callEndpoint(window.POS.cartPriceUrl, { product_id: line.product_id, unit_price: parseFloat(price) }, line);
            } else if (action === 'discount') {
                var rate = prompt('输入行折扣率（0~100）', line.discount_rate);
                if (rate === null) { return; }
                await callEndpoint(window.POS.cartDiscountUrl, { product_id: line.product_id, discount_rate: parseFloat(rate) }, line);
            } else if (action === 'remove') {
                if (!confirm('确定删除该行 ' + line.name + '？')) { return; }
                await callEndpoint(window.POS.cartRemoveUrl, { product_id: line.product_id }, line);
            }
        } catch (err) {
            setStatus(err.message || '操作失败，请重试');
        }
    }

    // 商品网格：事件委托，点击加购
    catalog.addEventListener('click', function (event) {
        var btn = event.target.closest('.pos-product');
        if (!btn || btn.disabled) { return; }
        addByProduct(Number(btn.dataset.productId), btn.dataset.traceability, btn.dataset.name);
    });

    // 购物车行：事件委托（按行索引定位，兼容同商品多批次行）
    cartRows.addEventListener('click', function (event) {
        var btn = event.target.closest('button[data-action]');
        if (!btn || btn.disabled) { return; }
        var tr = btn.closest('tr');
        handleRowAction(btn.dataset.action, Number(tr.dataset.index));
    });

    // ===== 客户选择 =====

    function renderCustomer() {
        if (!customerSelectedEl) { return; }

        if (!selectedCustomer) {
            customerSelectedEl.textContent = '未选择客户';
            customerSelectedEl.className = 'small text-muted mt-1';
            return;
        }

        customerSelectedEl.className = 'small mt-1 text-success';
        customerSelectedEl.textContent = '已选：' + selectedCustomer.name + '（' + selectedCustomer.code + '）' +
            (Number(selectedCustomer.credit_limit) > 0 ? ' · 信用额度 ' + money(selectedCustomer.credit_limit) : '');
    }

    function pickCustomer(customer) {
        selectedCustomer = customer;
        customerResults.classList.add('d-none');
        customerSearch.value = '';
        renderCustomer();

        if (customer.default_discount_rate > 0 && orderDiscountRate === 0) {
            orderDiscountRate = Math.min(100, Math.max(0, Number(customer.default_discount_rate)));
            if (orderDiscountInput) { orderDiscountInput.value = orderDiscountRate; }
            refreshOrderTotals();
            setStatus('已套用客户默认折扣 ' + orderDiscountRate + '%');
        }
    }

    function renderCustomerResults(customers) {
        customerResults.innerHTML = '';

        if (!customers.length) {
            customerResults.classList.add('d-none');
            return;
        }

        customers.forEach(function (customer) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-customer-option';
            btn.setAttribute('role', 'option');
            btn.textContent = customer.code + ' · ' + customer.name +
                (Number(customer.credit_limit) > 0 ? '（额度 ' + money(customer.credit_limit) + '）' : '');
            btn.addEventListener('click', function () { pickCustomer(customer); });
            customerResults.appendChild(btn);
        });

        customerResults.classList.remove('d-none');
    }

    if (customerSearch) {
        var customerTimer = null;
        customerSearch.addEventListener('input', function () {
            clearTimeout(customerTimer);
            var query = customerSearch.value.trim();
            if (!query) {
                customerResults.classList.add('d-none');
                return;
            }
            customerTimer = setTimeout(async function () {
                try {
                    var res = await window.shopFetch(window.POS.customersUrl + '?q=' + encodeURIComponent(query));
                    if (!res.ok) { return; }
                    var data = await res.json();
                    renderCustomerResults(data.customers || []);
                } catch (err) { /* 检索失败不阻塞收银 */ }
            }, 250);
        });
    }

    if (customerClear) {
        customerClear.addEventListener('click', function () {
            selectedCustomer = null;
            customerResults.classList.add('d-none');
            if (customerSearch) { customerSearch.value = ''; }
            renderCustomer();
        });
    }

    // ===== 支付面板（多笔支付 / 剩余 / 找零） =====

    function remainingAmount() {
        return Math.max(0, round2(lastGrand - paidTotal()));
    }

    function renderPaymentSummary() {
        var paid = paidTotal();
        var due = Math.max(0, round2(lastGrand - paid));
        var change = Math.max(0, round2(paid - lastGrand));

        if (paidEl) { paidEl.textContent = money(paid); }
        if (dueEl) { dueEl.textContent = money(due); }
        if (changeEl) { changeEl.textContent = money(change); }

        if (paymentQrBtn) {
            paymentQrBtn.disabled = !(lastGrand > 0 && due > 0);
        }
    }

    // ===== 动态收款二维码 =====

    async function generatePaymentQr(amount) {
        if (!window.POS.paymentQrUrl || !qrCanvas) { return; }
        try {
            var res = await window.shopFetch(window.POS.paymentQrUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount: round2(amount) })
            });
            if (!res.ok) { throw new Error(await readError(res, '生成收款码失败')); }

            var data = await res.json();
            qrCanvas.innerHTML = '';
            new window.QRCode(qrCanvas, {
                text: data.payload,
                width: 220,
                height: 220,
                correctLevel: window.QRCode.CorrectLevel.M
            });

            if (qrAmountEl) { qrAmountEl.textContent = money(data.amount); }
            if (qrTokenEl) { qrTokenEl.textContent = '收款单号 ' + data.token; }
            if (qrExpireEl) { qrExpireEl.textContent = '有效期至 ' + data.expires_at; }
        } catch (error) {
            setStatus(error.message || '生成收款码失败');
        }
    }

    function openPaymentQr() {
        if (!qrDialog) { return; }
        var amount = remainingAmount();
        if (amount <= 0.001) {
            setStatus('当前没有待收金额');
            return;
        }
        qrDialog.classList.remove('d-none');
        generatePaymentQr(amount);
    }

    function closePaymentQr() {
        if (qrDialog) { qrDialog.classList.add('d-none'); }
    }

    if (paymentQrBtn) { paymentQrBtn.addEventListener('click', openPaymentQr); }
    var qrCloseBtn = document.getElementById('pos-qr-close');
    if (qrCloseBtn) { qrCloseBtn.addEventListener('click', closePaymentQr); }
    var qrRefreshBtn = document.getElementById('pos-qr-refresh');
    if (qrRefreshBtn) {
        qrRefreshBtn.addEventListener('click', function () {
            var amount = remainingAmount();
            if (amount > 0.001) { generatePaymentQr(amount); }
        });
    }
    if (qrDialog) {
        qrDialog.addEventListener('click', function (event) {
            if (event.target === qrDialog) { closePaymentQr(); }
        });
    }

    function renderPayments() {
        if (!paymentRowsEl) { return; }
        paymentRowsEl.innerHTML = '';

        selectedPayments.forEach(function (payment, index) {
            var row = document.createElement('div');
            row.className = 'pos-payment-row';

            var amountInput = document.createElement('input');
            amountInput.type = 'number';
            amountInput.min = '0';
            amountInput.step = '0.01';
            amountInput.className = 'form-control form-control-sm pos-payment-amount';
            amountInput.value = Number(payment.amount || 0).toFixed(2);
            amountInput.setAttribute('aria-label', payment.name + ' 金额');
            amountInput.addEventListener('change', function () {
                var value = parseFloat(amountInput.value);
                payment.amount = isNaN(value) || value < 0 ? 0 : round2(value);
                renderPaymentSummary();
            });

            var label = document.createElement('span');
            label.className = 'small text-truncate';
            label.textContent = payment.name;

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-outline-danger btn-sm';
            remove.setAttribute('aria-label', '删除该笔支付');
            remove.innerHTML = '<i class="fa-solid fa-xmark"></i>';
            remove.addEventListener('click', function () {
                selectedPayments.splice(index, 1);
                renderPayments();
                renderPaymentSummary();
            });

            row.appendChild(label);
            row.appendChild(amountInput);
            row.appendChild(remove);

            if (payment.type !== 'cash') {
                var refWrap = document.createElement('div');
                refWrap.className = 'pos-payment-ref';

                var refInput = document.createElement('input');
                refInput.type = 'text';
                refInput.className = 'form-control form-control-sm';
                refInput.placeholder = '交易号 / 客户付款码（可扫码）';
                refInput.value = payment.reference || '';
                refInput.setAttribute('aria-label', payment.name + ' 交易号或付款码');
                refInput.addEventListener('change', function () {
                    payment.reference = refInput.value.trim() || null;
                });

                var refScan = document.createElement('button');
                refScan.type = 'button';
                refScan.className = 'btn btn-outline-secondary btn-sm';
                refScan.title = '扫描客户付款码';
                refScan.setAttribute('aria-label', '扫描客户付款码');
                refScan.innerHTML = '<i class="fa-solid fa-camera"></i>';
                refScan.addEventListener('click', function () {
                    if (!window.ErpScan) { return; }
                    window.ErpScan.scanCamera({
                        title: '扫描客户付款码',
                        onResult: function (value) {
                            refInput.value = String(value || '').trim();
                            payment.reference = refInput.value || null;
                            refInput.focus();
                        }
                    });
                });

                refWrap.appendChild(refInput);
                refWrap.appendChild(refScan);
                row.appendChild(refWrap);
            }

            paymentRowsEl.appendChild(row);
        });
    }

    function methodIcon(type) {
        if (type === 'cash') { return 'fa-money-bill-wave'; }
        if (type === 'card') { return 'fa-credit-card'; }
        if (type === 'credit') { return 'fa-hand-holding-dollar'; }
        return 'fa-qrcode';
    }

    function renderPaymentMethods(methods) {
        if (!paymentMethodsEl) { return; }
        paymentMethods = methods || [];
        paymentMethodsEl.innerHTML = '';

        paymentMethods.forEach(function (m) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-outline-primary';
            btn.dataset.methodId = m.id;
            btn.dataset.type = m.type;
            btn.innerHTML = '<i class="fa-solid ' + methodIcon(m.type) + '"></i> ' + escapeHtml(m.name);
            btn.addEventListener('click', function () { addPayment(m, btn); });
            paymentMethodsEl.appendChild(btn);
        });
    }

    function addPayment(m, btn) {
        if (m.type === 'credit' && !selectedCustomer) {
            setStatus('挂账支付需先选择客户');
            if (customerSearch) { customerSearch.focus(); }
            return;
        }

        var existing = selectedPayments.find(function (p) { return p.method_id === m.id; });
        if (existing) {
            if (remainingAmount() > 0 || existing.amount <= 0) {
                existing.amount = round2(existing.amount + remainingAmount());
            }
        } else {
            selectedPayments.push({
                method_id: m.id,
                type: m.type,
                name: m.name,
                amount: remainingAmount(),
                reference: null
            });
        }

        if (btn) { btn.classList.add('active'); }
        renderPayments();
        renderPaymentSummary();

        var amountInput = paymentRowsEl.querySelector('.pos-payment-row:last-child input');
        if (amountInput) { amountInput.focus(); amountInput.select(); }
    }

    async function loadPaymentMethods() {
        if (!paymentMethodsEl) { return; }
        try {
            var res = await window.shopFetch(window.POS.paymentMethodsUrl);
            if (!res.ok) { return; }
            var data = await res.json();
            renderPaymentMethods(data.methods || []);
        } catch (err) {
            // 支付方式加载失败不阻塞收银骨架
        }
    }

    async function checkout() {
        if (!cart.length) {
            setStatus('购物车为空');
            return;
        }
        if (!selectedPayments.length) {
            setStatus('请选择支付方式');
            return;
        }

        var needsCredit = selectedPayments.some(function (p) { return p.type === 'credit'; });
        if (needsCredit && !selectedCustomer) {
            setStatus('挂账支付需先选择客户');
            if (customerSearch) { customerSearch.focus(); }
            return;
        }

        var due = remainingAmount();
        if (due > 0.001) {
            setStatus('支付金额不足，仍差 ' + money(due));
            return;
        }

        var receiptWin = preopenReceiptWindow();

        try {
            var res = await window.shopFetch(window.POS.checkoutUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    lines: cart,
                    payments: selectedPayments.map(function (p) {
                        return { method_id: p.method_id, amount: Number(p.amount), reference: p.reference || null };
                    }),
                    customer_id: selectedCustomer ? selectedCustomer.id : null,
                    discount_rate: orderDiscountRate
                })
            });

            if (!res.ok) {
                throw new Error(await readError(res, '结账失败，请核对支付金额'));
            }

            var data = await res.json();
            var shift = data.order;

            cart = [];
            selectedPayments = [];
            selectedCustomer = null;
            orderDiscountRate = 0;
            if (orderDiscountInput) { orderDiscountInput.value = ''; }
            if (customerSearch) { customerSearch.value = ''; }
            renderCustomer();
            render();
            renderPayments();

            setStatus('小票 ' + shift.code + '，找零 ' + money(shift.change_amount));
            showReceiptLink(shift);

            var receiptUrl = receiptUrlFor(shift.id);
            if (receiptWin && !receiptWin.closed) {
                try {
                    receiptWin.location.replace(receiptUrl);
                    receiptWin.focus();
                } catch (e) {
                    showReceiptLink(shift);
                }
            } else {
                setStatus('小票 ' + shift.code + ' 已生成；浏览器拦截了自动弹窗，请点下方链接查看');
            }

            loadPaymentMethods();
            fetchShiftSummary();
        } catch (err) {
            if (receiptWin && !receiptWin.closed) {
                try { receiptWin.close(); } catch (e) { /* 忽略 */ }
            }
            setStatus(err.message || '结账失败');
        }
    }

    if (checkoutBtn) { checkoutBtn.addEventListener('click', checkout); }

    // ===== 挂单 / 取单 =====

    async function saveOrder() {
        if (!cart.length) {
            setStatus('购物车为空，不能挂单');
            return;
        }
        try {
            var res = await window.shopFetch(window.POS.savedSaveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lines: cart })
            });
            if (!res.ok) { throw new Error('挂单失败'); }
            cart = [];
            render();
            setStatus('已挂单');
        } catch (err) {
            setStatus(err.message || '挂单失败');
        }
    }

    async function restoreOrder() {
        try {
            var res = await window.shopFetch(window.POS.savedIndexUrl);
            if (!res.ok) { throw new Error('取单失败'); }
            var data = await res.json();
            var list = data.saved || [];
            if (!list.length) {
                setStatus('没有可取的挂单');
                return;
            }
            var picked = list[0];
            if (list.length > 1) {
                var input = prompt('多条挂单，输入序号 1~' + list.length, '1');
                var idx = parseInt(input, 10) - 1;
                if (isNaN(idx) || idx < 0 || idx >= list.length) { return; }
                picked = list[idx];
            }
            var restoreUrl = window.POS.savedRestoreUrl.replace('/0/', '/' + picked.id + '/');
            var r = await window.shopFetch(restoreUrl, { method: 'POST' });
            if (!r.ok) { throw new Error(await readError(r, '该挂单已过期或不可取')); }
            var d = await r.json();
            cart = d.cart || [];
            render();
        } catch (err) {
            setStatus(err.message || '取单失败');
        }
    }

    if (saveBtn) { saveBtn.addEventListener('click', saveOrder); }
    if (restoreBtn) { restoreBtn.addEventListener('click', restoreOrder); }

    // ===== 快捷键（F2 结账 / F3 挂单 / F4 取单） =====

    function isEditableTarget(el) {
        return el && (
            el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' ||
            el.tagName === 'SELECT' || el.isContentEditable
        );
    }

    var FKEYS = { F2: 113, F3: 114, F4: 115 };

    document.addEventListener('keydown', function (event) {
        // 中文输入法组词（composition）期间不拦截按键，避免打断上屏
        if (event.isComposing || event.keyCode === 229) { return; }

        var matched = null;
        Object.keys(FKEYS).forEach(function (name) {
            if (event.key === name || event.keyCode === FKEYS[name]) { matched = name; }
        });
        if (!matched) { return; }

        // 输入框 / 下拉聚焦时不触发功能键，避免录入数字时误结账、误挂单
        if (isEditableTarget(event.target)) { return; }

        event.preventDefault();

        if (matched === 'F2') {
            if (checkoutBtn && !checkoutBtn.disabled) { checkout(); }
        } else if (matched === 'F3') {
            if (saveBtn && !saveBtn.disabled) { saveOrder(); }
        } else if (matched === 'F4') {
            if (restoreBtn && !restoreBtn.disabled) { restoreOrder(); }
        }
    });

    async function fetchShiftSummary() {
        var countEl = document.getElementById('pos-shift-count');
        if (!countEl || !window.POS.summaryUrl) { return; }
        try {
            var res = await window.shopFetch(window.POS.summaryUrl);
            if (!res.ok) { return; }
            var data = await res.json();
            var s = data.summary || {};

            countEl.textContent = s.order_count || 0;
            document.getElementById('pos-shift-sales').textContent = money(s.total_sales);
            document.getElementById('pos-shift-discount').textContent = money(s.total_discount);
            document.getElementById('pos-shift-cash').textContent = money(s.expected_cash);

            var parts = [];
            if (Number(s.total_refund || 0) > 0) {
                parts.push('退款 ' + money(s.total_refund));
            }
            Object.keys(s.payments || {}).forEach(function (type) {
                parts.push((PAYMENT_LABELS[type] || type) + ' ' + money(s.payments[type]));
            });
            var payEl = document.getElementById('pos-shift-payments');
            if (payEl) { payEl.textContent = parts.join(' · '); }

            if (shiftExpectedEl) { shiftExpectedEl.textContent = money(s.expected_cash); }
        } catch (err) {
            // 汇总刷新失败不阻塞收银主流程
        }
    }

    // ===== 交班清点 =====

    var DENOMINATIONS = [100, 50, 20, 10, 5, 1, 0.5, 0.1];

    function buildShiftDenoms() {
        if (!shiftDenomsEl) { return; }
        shiftDenomsEl.innerHTML = '';

        DENOMINATIONS.forEach(function (value) {
            var wrapper = document.createElement('div');
            wrapper.className = 'col-6';

            var row = document.createElement('div');
            row.className = 'pos-shift-denom';

            var label = document.createElement('label');
            label.className = 'small mb-0 flex-grow-1';
            label.textContent = money(value);

            var input = document.createElement('input');
            input.type = 'number';
            input.min = '0';
            input.step = '1';
            input.className = 'form-control form-control-sm';
            input.style.width = '5rem';
            input.dataset.denomination = value;
            input.value = '0';
            input.setAttribute('aria-label', money(value) + ' 张数');
            input.addEventListener('input', updateCountedCash);

            label.setAttribute('for', 'pos-denom-' + String(value).replace('.', '_'));
            input.id = label.getAttribute('for');

            row.appendChild(label);
            row.appendChild(input);
            wrapper.appendChild(row);
            shiftDenomsEl.appendChild(wrapper);
        });
    }

    function countedCash() {
        if (!shiftDenomsEl) { return 0; }
        var sum = 0;
        shiftDenomsEl.querySelectorAll('input[data-denomination]').forEach(function (input) {
            var qty = parseInt(input.value, 10);
            sum += (isNaN(qty) ? 0 : qty) * Number(input.dataset.denomination);
        });
        return round2(sum);
    }

    function updateCountedCash() {
        if (shiftCountedEl) { shiftCountedEl.textContent = money(countedCash()); }
    }

    function openShiftDialog() {
        if (!shiftDialog) { return; }
        buildShiftDenoms();
        updateCountedCash();
        if (shiftNote) { shiftNote.value = ''; }
        shiftDialog.classList.remove('d-none');
        fetchShiftSummary();
    }

    function closeShiftDialog() {
        if (shiftDialog) { shiftDialog.classList.add('d-none'); }
    }

    if (closeShiftBtn) {
        closeShiftBtn.addEventListener('click', openShiftDialog);
    }

    if (shiftCancel) {
        shiftCancel.addEventListener('click', closeShiftDialog);
    }

    if (shiftConfirm) {
        shiftConfirm.addEventListener('click', async function () {
            var denominations = [];
            shiftDenomsEl.querySelectorAll('input[data-denomination]').forEach(function (input) {
                var qty = parseInt(input.value, 10);
                if (qty > 0) {
                    denominations.push({
                        denomination: Number(input.dataset.denomination),
                        count_qty: qty
                    });
                }
            });

            try {
                shiftConfirm.disabled = true;
                var res = await window.shopFetch(window.POS.closeShiftUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        denominations: denominations,
                        note: shiftNote ? shiftNote.value : null
                    })
                });

                if (!res.ok) {
                    throw new Error(await readError(res, '交班失败'));
                }

                var data = await res.json();
                var s = data.session || {};
                var variance = Number(s.variance_amount || 0);
                var varianceText = variance === 0 ? '无长短款'
                    : (variance > 0 ? '长款 ' + money(variance) : '短款 ' + money(Math.abs(variance)));

                closeShiftDialog();
                window.alert('交班完成：应收现金 ' + money(s.expected_cash) +
                    '，实点 ' + money(s.counted_cash) + '，' + varianceText);
                window.location.reload();
            } catch (err) {
                setStatus(err.message || '交班失败');
                shiftConfirm.disabled = false;
            }
        });
    }

    // ===== 初始化 =====

    loadPaymentMethods();
    renderPayments();
    renderPaymentSummary();
    renderCustomer();
    render();
    fetchShiftSummary();
    setInterval(fetchShiftSummary, 15000);

    var posScanBtn = document.getElementById('pos-scan-btn');
    if (posScanBtn) {
        posScanBtn.addEventListener('click', function () {
            if (!window.ErpScan) { return; }
            window.ErpScan.scanCamera({
                title: '扫描商品 / 批次 / 序列号',
                onResult: function (value) {
                    searchInput.value = String(value || '').trim();
                    matchAndAdd();
                }
            });
        });
    }

    if (searchInput) {
        var debounceTimer = null;
        searchInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                renderCatalog(searchInput.value.trim());
            }, 200);
        });

        // 中文输入法组词（composition）期间不拦截回车，避免打断上屏
        searchInput.addEventListener('keydown', function (event) {
            if (event.isComposing || event.keyCode === 229) { return; }
            if (event.key === 'Enter') {
                event.preventDefault();
                matchAndAdd();
            }
        });
    }

    if (orderDiscountInput) {
        var orderDiscountTimer = null;
        orderDiscountInput.addEventListener('input', function () {
            clearTimeout(orderDiscountTimer);
            orderDiscountTimer = setTimeout(function () {
                var value = parseFloat(orderDiscountInput.value);
                orderDiscountRate = isNaN(value) ? 0 : Math.min(100, Math.max(0, value));
                refreshOrderTotals();
            }, 200);
        });
    }
})();
