@extends('shop::layouts.app')

@section('title', '仪表盘')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">经营仪表盘</h1>
            <p class="text-muted mb-0 small">数据日期 {{ $today }} · 近 14 天趋势 / 近 30 天结构 · 全部取自本地数据库</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('shop.pos.index') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-cash-register me-1"></i>去收银</a>
            <a href="{{ route('shop.pos.orders.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-receipt me-1"></i>POS 订单</a>
            <a href="{{ route('shop.profit-stock.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-chart-line me-1"></i>库存报表</a>
        </div>
    </div>

    @php
        $canOpenGatewaySetup = auth()->user()->roles()->where('code', 'super-admin')->exists()
            || auth()->user()->hasPermission('system.view');
    @endphp

    @if ($canOpenGatewaySetup && $paymentGatewaySummary['pending'])
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2" role="status">
            <div>
                <i class="fa-solid fa-plug-circle-check me-1"></i>
                <strong>支付接入待完成</strong>
                <span class="small ms-1">
                    微信 / 支付宝已通过检测 {{ $paymentGatewaySummary['ready'] }} / {{ $paymentGatewaySummary['total'] }} 个。
                    @if ($paymentGatewaySummary['configured'] === 0)
                        首次运行可先完成商户资料登记与网关连通性检查。
                    @endif
                </span>
            </div>
            <a href="{{ route('shop.payment-gateways.index') }}" class="btn btn-sm btn-primary">
                去完成接入 <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4 col-xl-2">
            <div class="card h-100 dashboard-stat">
                <div class="card-body d-flex justify-content-between gap-2">
                    <div class="min-w-0">
                        <p class="text-muted small mb-1">今日 POS 销售额</p>
                        <p class="h5 mb-1 text-nowrap">￥{{ number_format($posSales, 2) }}</p>
                        <p class="small text-muted mb-0">{{ $posCount }} 笔已完成</p>
                    </div>
                    <span class="dashboard-stat-icon tone-primary"><i class="fa-solid fa-cash-register"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4 col-xl-2">
            <div class="card h-100 dashboard-stat">
                <div class="card-body d-flex justify-content-between gap-2">
                    <div class="min-w-0">
                        <p class="text-muted small mb-1">今日销售订单额</p>
                        <p class="h5 mb-1 text-nowrap">￥{{ number_format($salesAmount, 2) }}</p>
                        <p class="small text-muted mb-0">{{ $salesCount }} 笔已确认/发货</p>
                    </div>
                    <span class="dashboard-stat-icon tone-success"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4 col-xl-2">
            <div class="card h-100 dashboard-stat">
                <div class="card-body d-flex justify-content-between gap-2">
                    <div class="min-w-0">
                        <p class="text-muted small mb-1">应收余额</p>
                        <p class="h5 mb-1 text-nowrap">￥{{ number_format($receivable, 2) }}</p>
                        <p class="small text-muted mb-0">未结清发票合计</p>
                    </div>
                    <span class="dashboard-stat-icon tone-warning"><i class="fa-solid fa-hand-holding-dollar"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4 col-xl-2">
            <div class="card h-100 dashboard-stat">
                <div class="card-body d-flex justify-content-between gap-2">
                    <div class="min-w-0">
                        <p class="text-muted small mb-1">待办</p>
                        <p class="h5 mb-1">{{ $pendingShip + $pendingReceive }} 项</p>
                        <p class="small text-muted mb-0">发货 {{ $pendingShip }} · 收货 {{ $pendingReceive }}</p>
                    </div>
                    <span class="dashboard-stat-icon tone-danger"><i class="fa-solid fa-list-check"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4 col-xl-2">
            <div class="card h-100 dashboard-stat">
                <div class="card-body d-flex justify-content-between gap-2">
                    <div class="min-w-0">
                        <p class="text-muted small mb-1">库存总价值</p>
                        <p class="h5 mb-1 text-nowrap">￥{{ number_format($stockValue, 2) }}</p>
                        <p class="small text-muted mb-0">库存数量 {{ number_format($stockQty, 0) }}</p>
                    </div>
                    <span class="dashboard-stat-icon tone-primary"><i class="fa-solid fa-boxes-stacked"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4 col-xl-2">
            <div class="card h-100 dashboard-stat">
                <div class="card-body d-flex justify-content-between gap-2">
                    <div class="min-w-0">
                        <p class="text-muted small mb-1">今日客单价</p>
                        <p class="h5 mb-1 text-nowrap">￥{{ number_format($avgTicket, 2) }}</p>
                        <p class="small text-muted mb-0">POS 单均金额</p>
                    </div>
                    <span class="dashboard-stat-icon tone-success"><i class="fa-solid fa-receipt"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="fa-solid fa-chart-line me-1"></i>近 14 天销售趋势</span>
                    <span class="small text-muted">POS 销售额 vs 销售订单额</span>
                </div>
                <div class="card-body">
                    <div class="dashboard-chart">
                        <canvas id="salesTrendChart" role="img" aria-label="近 14 天 POS 销售额与销售订单额趋势折线图"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="fa-solid fa-wallet me-1"></i>支付方式构成</span>
                    <span class="small text-muted">近 30 天</span>
                </div>
                <div class="card-body">
                    @if (count($paymentMix['labels']) > 0 && $paymentMix['total'] > 0)
                        <div class="dashboard-chart">
                            <canvas id="paymentMixChart" role="img" aria-label="近 30 天 POS 收款按支付方式占比环形图"></canvas>
                        </div>
                    @else
                        <div class="dashboard-chart-empty">
                            <i class="fa-solid fa-wallet"></i>
                            <span>近 30 天暂无 POS 收款</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="fa-solid fa-warehouse me-1"></i>库存价值分布</span>
                    <span class="small text-muted">按仓库</span>
                </div>
                <div class="card-body">
                    @if (count($stockByWarehouse['labels']) > 0 && $stockByWarehouse['total'] > 0)
                        <div class="dashboard-chart">
                            <canvas id="stockWarehouseChart" role="img" aria-label="各仓库库存价值条形图"></canvas>
                        </div>
                    @else
                        <div class="dashboard-chart-empty">
                            <i class="fa-solid fa-boxes-stacked"></i>
                            <span>暂无库存金额</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="fa-solid fa-ranking-star me-1"></i>热销商品 Top 8</span>
                    <span class="small text-muted">近 30 天销量</span>
                </div>
                <div class="card-body">
                    @if (count($topProducts['labels']) > 0)
                        <div class="dashboard-chart">
                            <canvas id="topProductsChart" role="img" aria-label="近 30 天热销商品销量排行条形图"></canvas>
                        </div>
                    @else
                        <div class="dashboard-chart-empty">
                            <i class="fa-solid fa-ranking-star"></i>
                            <span>近 30 天暂无商品销量</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-4">
            <div class="card h-100">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span><i class="fa-solid fa-hourglass-half me-1"></i>应收账龄结构</span>
                    <a href="{{ route('shop.aging.index', ['type' => 'receivable']) }}" class="small">账龄明细</a>
                </div>
                <div class="card-body">
                    @if ($arAging['total'] > 0)
                        <div class="dashboard-chart">
                            <canvas id="arAgingChart" role="img" aria-label="应收余额按账龄分段条形图"></canvas>
                        </div>
                    @else
                        <div class="dashboard-chart-empty">
                            <i class="fa-solid fa-hourglass-half"></i>
                            <span>暂无应收余额</span>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>最近 POS 订单</span>
                    <a href="{{ route('shop.pos.orders.index') }}" class="small">查看全部</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <caption class="visually-hidden">最近 8 笔 POS 订单</caption>
                        <thead>
                            <tr>
                                <th scope="col">单号</th>
                                <th scope="col">时间</th>
                                <th scope="col">收银员</th>
                                <th scope="col" class="text-end">金额</th>
                                <th scope="col">状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentOrders as $order)
                                <tr>
                                    <td><a href="{{ route('shop.pos.orders.show', ['posOrder' => $order->id]) }}">{{ $order->code }}</a></td>
                                    <td class="small text-muted">{{ optional($order->order_date)->format('m-d H:i') }}</td>
                                    <td class="small">{{ $order->cashier?->name ?? '—' }}</td>
                                    <td class="text-end">￥{{ number_format((float) $order->grand_total, 2) }}</td>
                                    <td>
                                        @if ($order->status === \App\Modules\Shop\Models\PosOrder::STATUS_COMPLETED)
                                            <span class="badge text-bg-success">已完成</span>
                                        @elseif ($order->status === \App\Modules\Shop\Models\PosOrder::STATUS_REFUNDED)
                                            <span class="badge text-bg-warning">已退款</span>
                                        @else
                                            <span class="badge text-bg-secondary">{{ $order->status }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center py-4">暂无订单</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>低库存预警（可用量 ≤ 5）</span>
                    <a href="{{ route('shop.profit-stock.index') }}" class="small">库存报表</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <caption class="visually-hidden">可用量不足的商品</caption>
                        <thead>
                            <tr>
                                <th scope="col">编码</th>
                                <th scope="col">商品</th>
                                <th scope="col" class="text-end">可用</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lowStock as $item)
                                <tr>
                                    <td class="small">{{ $item['code'] }}</td>
                                    <td class="small text-truncate" style="max-width: 12rem">{{ $item['name'] }}</td>
                                    <td class="text-end">{{ $item['available'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted text-center py-4">库存充足</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>最近审计留痕</span>
                    <a href="{{ route('shop.audit-logs.index') }}" class="small">查看全部</a>
                </div>
                <div class="card-body py-2">
                    <ul class="list-unstyled small mb-0">
                        @forelse ($recentAudits as $log)
                            <li class="py-1 border-bottom" style="border-color: var(--color-border-subtle)">
                                <span class="text-muted">{{ optional($log->acted_at)->format('m-d H:i') }}</span>
                                <span class="badge text-bg-secondary ms-1">{{ $log->module }}</span>
                                <span class="ms-1">{{ \Illuminate\Support\Str::limit($log->message, 40) }}</span>
                            </li>
                        @empty
                            <li class="text-muted py-3">暂无审计记录</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset(config('shop.assets.chartjs.js')) }}"></script>
    <script>
        (function () {
            if (typeof Chart === 'undefined') {
                return;
            }

            const trend = @json($salesTrend);
            const payments = @json($paymentMix);
            const warehouseStock = @json($stockByWarehouse);
            const products = @json($topProducts);
            const aging = @json($arAging);

            const rootStyles = getComputedStyle(document.documentElement);
            const theme = {
                text: rootStyles.getPropertyValue('--color-text').trim() || '#202124',
                muted: rootStyles.getPropertyValue('--color-text-muted').trim() || '#5f6368',
                border: rootStyles.getPropertyValue('--color-border-subtle').trim() || '#dadce0',
                surface: rootStyles.getPropertyValue('--color-surface').trim() || '#ffffff',
                primary: rootStyles.getPropertyValue('--color-primary').trim() || '#0b57d0',
                success: rootStyles.getPropertyValue('--color-success').trim() || '#1e7e34',
                warning: rootStyles.getPropertyValue('--color-warning').trim() || '#b06000',
                danger: rootStyles.getPropertyValue('--color-danger').trim() || '#c5221f',
            };

            const withAlpha = (color, alpha) => {
                if (!color.startsWith('#')) {
                    return color;
                }
                let hex = color.slice(1);
                if (hex.length === 3) {
                    hex = hex.split('').map((char) => char + char).join('');
                }
                const value = parseInt(hex, 16);
                if (Number.isNaN(value)) {
                    return color;
                }
                return `rgba(${(value >> 16) & 255}, ${(value >> 8) & 255}, ${value & 255}, ${alpha})`;
            };

            const money = (value) => '¥' + Number(value).toLocaleString('zh-CN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });

            const compactMoney = (value) => {
                const number = Number(value);
                if (Math.abs(number) >= 10000) {
                    return '¥' + (number / 10000).toFixed(1) + '万';
                }
                return '¥' + number.toLocaleString('zh-CN', { maximumFractionDigits: 0 });
            };

            const commonPlugins = {
                legend: {
                    labels: {
                        color: theme.text,
                        boxWidth: 10,
                        usePointStyle: true,
                        font: { size: 11 },
                    },
                },
                tooltip: {
                    backgroundColor: theme.surface,
                    titleColor: theme.text,
                    bodyColor: theme.text,
                    borderColor: theme.border,
                    borderWidth: 1,
                    padding: 10,
                },
            };

            const axis = (moneyAxis) => ({
                grid: { color: theme.border, drawTicks: false },
                border: { color: theme.border },
                ticks: {
                    color: theme.muted,
                    font: { size: 10 },
                    callback: moneyAxis ? compactMoney : undefined,
                },
            });

            Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
            Chart.defaults.color = theme.text;

            new Chart(document.getElementById('salesTrendChart'), {
                type: 'line',
                data: {
                    labels: trend.labels,
                    datasets: [
                        {
                            label: 'POS 销售额',
                            data: trend.pos,
                            borderColor: theme.primary,
                            backgroundColor: withAlpha(theme.primary, 0.12),
                            fill: true,
                            tension: 0.35,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 4,
                        },
                        {
                            label: '销售订单额',
                            data: trend.sales,
                            borderColor: theme.success,
                            backgroundColor: withAlpha(theme.success, 0.06),
                            fill: false,
                            tension: 0.35,
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        ...commonPlugins,
                        tooltip: {
                            ...commonPlugins.tooltip,
                            callbacks: {
                                label: (context) => `${context.dataset.label}：${money(context.parsed.y)}`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            border: { color: theme.border },
                            ticks: { color: theme.muted, font: { size: 10 }, maxRotation: 0 },
                        },
                        y: axis(true),
                    },
                },
            });

            if (document.getElementById('paymentMixChart')) {
                const paymentColors = [
                    theme.primary,
                    theme.success,
                    theme.warning,
                    theme.danger,
                    theme.muted,
                    '#764ba2',
                    '#4facfe',
                    '#fa709a',
                ];

                new Chart(document.getElementById('paymentMixChart'), {
                    type: 'doughnut',
                    data: {
                        labels: payments.labels,
                        datasets: [{
                            data: payments.values,
                            backgroundColor: paymentColors.slice(0, payments.labels.length),
                            borderColor: theme.surface,
                            borderWidth: 2,
                            hoverOffset: 6,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '58%',
                        plugins: {
                            ...commonPlugins,
                            legend: {
                                ...commonPlugins.legend,
                                position: 'bottom',
                            },
                            tooltip: {
                                ...commonPlugins.tooltip,
                                callbacks: {
                                    label: (context) => {
                                        const value = Number(context.parsed);
                                        const percent = payments.total > 0 ? (value / payments.total * 100).toFixed(1) : '0.0';
                                        return `${context.label}：${money(value)}（${percent}%）`;
                                    },
                                },
                            },
                        },
                    },
                });
            }

            if (document.getElementById('stockWarehouseChart')) {
                new Chart(document.getElementById('stockWarehouseChart'), {
                    type: 'bar',
                    data: {
                        labels: warehouseStock.labels,
                        datasets: [{
                            label: '库存金额',
                            data: warehouseStock.values,
                            backgroundColor: withAlpha(theme.primary, 0.75),
                            borderColor: theme.primary,
                            borderWidth: 1,
                            borderRadius: 6,
                            barThickness: 16,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            ...commonPlugins,
                            legend: { display: false },
                            tooltip: {
                                ...commonPlugins.tooltip,
                                callbacks: {
                                    label: (context) => `库存金额：${money(context.parsed.x)}`,
                                    afterLabel: (context) => '库存数量：' + Number(warehouseStock.quantities[context.dataIndex]).toLocaleString('zh-CN'),
                                },
                            },
                        },
                        scales: {
                            x: axis(true),
                            y: {
                                grid: { display: false },
                                border: { color: theme.border },
                                ticks: { color: theme.muted, font: { size: 10 } },
                            },
                        },
                    },
                });
            }

            if (document.getElementById('topProductsChart')) {
                new Chart(document.getElementById('topProductsChart'), {
                    type: 'bar',
                    data: {
                        labels: products.labels,
                        datasets: [{
                            label: '销量',
                            data: products.values,
                            backgroundColor: withAlpha(theme.success, 0.72),
                            borderColor: theme.success,
                            borderWidth: 1,
                            borderRadius: 6,
                            barThickness: 14,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            ...commonPlugins,
                            legend: { display: false },
                            tooltip: {
                                ...commonPlugins.tooltip,
                                callbacks: {
                                    label: (context) => `销量：${Number(context.parsed.x).toLocaleString('zh-CN')}`,
                                },
                            },
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: theme.border, drawTicks: false },
                                border: { color: theme.border },
                                ticks: { color: theme.muted, font: { size: 10 } },
                            },
                            y: {
                                grid: { display: false },
                                border: { color: theme.border },
                                ticks: { color: theme.muted, font: { size: 10 } },
                            },
                        },
                    },
                });
            }

            if (document.getElementById('arAgingChart')) {
                const agingColors = [theme.primary, theme.success, theme.warning, theme.danger, theme.muted];

                new Chart(document.getElementById('arAgingChart'), {
                    type: 'bar',
                    data: {
                        labels: aging.labels,
                        datasets: [{
                            label: '应收余额',
                            data: aging.values,
                            backgroundColor: agingColors.map((color) => withAlpha(color, 0.72)),
                            borderColor: agingColors,
                            borderWidth: 1,
                            borderRadius: 6,
                            barThickness: 16,
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            ...commonPlugins,
                            legend: { display: false },
                            tooltip: {
                                ...commonPlugins.tooltip,
                                callbacks: {
                                    label: (context) => `应收余额：${money(context.parsed.x)}`,
                                },
                            },
                        },
                        scales: {
                            x: axis(true),
                            y: {
                                grid: { display: false },
                                border: { color: theme.border },
                                ticks: { color: theme.muted, font: { size: 10 } },
                            },
                        },
                    },
                });
            }
        })();
    </script>
@endpush
