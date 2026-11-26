<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>小票 {{ $data['order_code'] }}</title>
    <style>
        :root {
            --ink: #111;
            --muted: #555;
            --line: #999;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #f3f3f3;
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", "Microsoft YaHei", sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }
        .toolbar {
            max-width: 480px;
            margin: 16px auto 0;
            display: flex;
            gap: 8px;
            justify-content: space-between;
            align-items: center;
        }
        .toolbar a, .toolbar button {
            font: inherit;
            padding: 6px 12px;
            border: 1px solid #888;
            border-radius: 6px;
            background: #fff;
            color: var(--ink);
            text-decoration: none;
            cursor: pointer;
        }
        .receipt {
            max-width: 480px;
            margin: 16px auto;
            padding: 20px 18px;
            background: #fff;
            border: 1px solid var(--line);
            font-family: "Courier New", ui-monospace, Consolas, "Microsoft YaHei", monospace;
        }
        .receipt h1 {
            font-size: 18px;
            text-align: center;
            margin: 0 0 8px;
            font-weight: 700;
        }
        .meta { margin: 0 0 8px; }
        .meta div { display: flex; justify-content: space-between; gap: 12px; }
        .rule { border-top: 1px dashed var(--line); margin: 8px 0; }
        .items { margin: 8px 0; }
        .item { margin-bottom: 6px; }
        .item .line { display: flex; justify-content: space-between; gap: 10px; }
        .item .sub { font-size: 12px; color: var(--muted); }
        .totals .row { display: flex; justify-content: space-between; gap: 12px; }
        .totals .grand { font-weight: 700; font-size: 15px; }
        .payments .row { display: flex; justify-content: space-between; gap: 12px; }
        .foot { text-align: center; margin-top: 12px; color: var(--muted); font-size: 12px; white-space: pre-line; }
        .barcode-block {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin: 8px 0 4px;
            padding: 6px 8px;
            border-top: 1px dashed var(--line);
            border-bottom: 1px dashed var(--line);
        }
        .barcode-block canvas { max-width: 220px; height: 52px; }
        .barcode-block .order-qr { width: 84px; height: 84px; }
        .barcode-text { text-align: center; font-size: 11px; letter-spacing: 1px; color: var(--muted); }

        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .receipt { border: none; max-width: 100%; margin: 0; padding: 0; }
            @page { margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <a href="{{ route('shop.pos.orders.show', $order) }}">返回订单</a>
        <button type="button" onclick="window.print()">打印小票</button>
    </div>

    <section class="receipt" aria-label="收银小票">
        <h1>{{ $data['store_name'] }}</h1>

        @if (! empty($data['address']))
            <div class="foot" style="margin-top: 0;">{{ $data['address'] }}</div>
        @endif
        @if (! empty($data['phone']))
            <div class="foot" style="margin-top: 0;">电话：{{ $data['phone'] }}</div>
        @endif

        <div class="meta">
            <div><span>单号</span><span>{{ $data['order_code'] }}</span></div>
            <div><span>时间</span><span>{{ $data['order_date'] }}</span></div>
            @if ($data['show_cashier'] ?? true)
                <div><span>收银员</span><span>{{ $data['cashier'] }}</span></div>
            @endif
            <div><span>客户</span><span>{{ $data['customer'] }}</span></div>
        </div>

        @if ($data['show_barcode'] ?? false)
            <div class="barcode-block">
                <canvas class="order-barcode" data-code="{{ $data['order_code'] }}"
                        aria-label="订单条码 {{ $data['order_code'] }}"></canvas>
                <div class="order-qr" data-qr="ERP-ORDER:{{ $data['order_code'] }}"
                     aria-label="订单二维码 {{ $data['order_code'] }}"></div>
            </div>
            <div class="barcode-text">{{ $data['order_code'] }}</div>
        @endif

        <div class="rule"></div>

        <div class="items">
            @foreach ($data['items'] as $item)
                <div class="item">
                    <div class="line">
                        <span>{{ $item['name'] }}</span>
                        <span>{{ number_format((float) $item['amount'], 2) }}</span>
                    </div>
                    <div class="sub">
                        {{ $item['code'] ? $item['code'] . ' · ' : '' }}{{ rtrim(rtrim(number_format((float) $item['qty'], 2), '0'), '.') }} × {{ number_format((float) $item['price'], 2) }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="rule"></div>

        <div class="totals">
            @if ((float) $data['discount'] > 0 && ($data['show_discount'] ?? true))
                <div class="row"><span>折扣</span><span>-{{ number_format((float) $data['discount'], 2) }}</span></div>
            @endif
            @if ((float) $data['tax'] > 0)
                <div class="row"><span>税额</span><span>{{ number_format((float) $data['tax'], 2) }}</span></div>
            @endif
            <div class="row grand"><span>合计</span><span>{{ number_format((float) $data['total'], 2) }}</span></div>
        </div>

        <div class="rule"></div>

        <div class="payments">
            @foreach ($data['payments'] as $payment)
                <div class="row">
                    <span>{{ $payment['method'] }}</span>
                    <span>{{ number_format((float) $payment['amount'], 2) }}{{ $payment['reference'] ? ' (' . $payment['reference'] . ')' : '' }}</span>
                </div>
            @endforeach
            @if ((float) $data['change'] > 0)
                <div class="row"><span>找零</span><span>{{ number_format((float) $data['change'], 2) }}</span></div>
            @endif
        </div>

        <div class="rule"></div>
        <div class="foot">{{ $data['footer'] ?? '谢谢惠顾，欢迎再次光临' }}</div>
    </section>

    <script src="{{ asset(config('shop.assets.jsbarcode.js')) }}"></script>
    <script src="{{ asset(config('shop.assets.qrcode.js')) }}"></script>
    <script>
        (function () {
            'use strict';

            document.querySelectorAll('.order-barcode').forEach(function (canvas) {
                var code = canvas.dataset.code;
                try {
                    window.JsBarcode(canvas, code, {
                        format: 'CODE39', width: 1.4, height: 48, displayValue: false, margin: 0
                    });
                } catch (error) {
                    window.JsBarcode(canvas, code, {
                        format: 'CODE128', width: 1.4, height: 48, displayValue: false, margin: 0
                    });
                }
            });

            document.querySelectorAll('[data-qr]').forEach(function (element) {
                if (!window.QRCode) { return; }
                new window.QRCode(element, {
                    text: element.dataset.qr,
                    width: 84,
                    height: 84,
                    correctLevel: window.QRCode.CorrectLevel.M
                });
            });
        })();
    </script>

    @if (request()->boolean('auto'))
        <script>
            window.addEventListener('load', function () {
                // 终端开启「结账后自动打印」时，等页面渲染完成后自动调起系统打印对话框
                window.setTimeout(function () { window.print(); }, 150);
            });
        </script>
    @endif
</body>
</html>
