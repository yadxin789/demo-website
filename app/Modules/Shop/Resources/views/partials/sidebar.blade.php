@php
    $badges = \App\Modules\Shop\Support\MenuBadges::counts();

    // 当前路由所属分组，用于页面加载时只展开对应手风琴面板
    $gBase = Route::is('products.*') || Route::is('categories.*') || Route::is('units.*')
        || Route::is('taxes.*') || Route::is('payment-methods.*') || Route::is('warehouses.*')
        || Route::is('locations.*') || Route::is('suppliers.*') || Route::is('customers.*')
        || Route::is('price-lists.*') || Route::is('sequences.*') || Route::is('products.labels.*');
    $gPurchase = Route::is('purchase-orders.*') || Route::is('receipts.*') || Route::is('purchase-returns.*');
    $gStock = Route::is('movements.*') || Route::is('warehouse.putaway.*') || Route::is('counts.*')
        || Route::is('scraps.*') || Route::is('lots.*') || Route::is('serials.*');
    $gSales = Route::is('sales-returns.*');
    $gFinance = Route::is('statements.*') || Route::is('reconciliations.*') || Route::is('tax-reports.*')
        || Route::is('closing.*') || Route::is('expenses.*') || Route::is('aging.*');
    $gReport = Route::is('financial-reports.*') || Route::is('profit-stock.*');
    $gSetting = Route::is('automation.*') || Route::is('ai.settings') || Route::is('ai.welcome')
        || Route::is('payment-gateways.*') || Route::is('audit-logs.*') || Route::is('backups.*');
@endphp
<aside class="app-sidebar" aria-label="主导航">
    <div class="d-flex align-items-center gap-1 px-3 mb-3">
        <i class="fa-solid fa-store"></i>
        <strong>{{ config('shop.name') }}</strong>
    </div>
    <nav aria-label="模块菜单">
        <ul id="sidebar-nav" class="nav flex-column gap-1 px-2 list-unstyled">
            <li class="nav-item">
                <a class="nav-link" href="{{ route('shop.dashboard') }}">
                    <i class="fa-solid fa-gauge-high sidebar-menu-icon"></i>仪表盘
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link @if(Route::is('ai.chat') || Route::is('ai.docs') || Route::is('ai.notice')) active @endif" href="{{ route('shop.ai.chat') }}">
                    <i class="fa-solid fa-robot sidebar-menu-icon"></i>AI 智能助手
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if(Route::is('ai-delegation.*')) active @endif" href="{{ route('shop.ai-delegation.index') }}">
                    <i class="fa-solid fa-user-shield sidebar-menu-icon"></i>AI 授权中心
                </a>
            </li>

            {{-- 基础资料 --}}
            <li class="nav-item nav-group">
                <a class="nav-link nav-group-toggle" href="#sub-base"
                   data-bs-toggle="collapse" data-bs-target="#sub-base" data-bs-parent="#sidebar-nav"
                   aria-expanded="{{ $gBase ? 'true' : 'false' }}">
                    <i class="fa-solid fa-boxes-stacked sidebar-menu-icon"></i>基础资料
                    <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
                </a>
                <ul class="collapse @if($gBase)show @endif nav flex-column gap-1 ps-4 list-unstyled"
                    id="sub-base" data-bs-parent="#sidebar-nav">
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('products.*')) active @endif" href="{{ route('shop.products.index') }}">
                            <i class="fa-solid fa-box sidebar-menu-icon"></i>商品
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('categories.*')) active @endif" href="{{ route('shop.categories.index') }}">
                            <i class="fa-solid fa-sitemap sidebar-menu-icon"></i>商品类别
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('units.*')) active @endif" href="{{ route('shop.units.index') }}">
                            <i class="fa-solid fa-ruler sidebar-menu-icon"></i>计量单位
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('taxes.*')) active @endif" href="{{ route('shop.taxes.index') }}">
                            <i class="fa-solid fa-percent sidebar-menu-icon"></i>税率
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('payment-methods.*')) active @endif" href="{{ route('shop.payment-methods.index') }}">
                            <i class="fa-solid fa-credit-card sidebar-menu-icon"></i>支付方式
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('warehouses.*')) active @endif" href="{{ route('shop.warehouses.index') }}">
                            <i class="fa-solid fa-warehouse sidebar-menu-icon"></i>仓库
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('locations.*')) active @endif" href="{{ route('shop.locations.index') }}">
                            <i class="fa-solid fa-map-location-dot sidebar-menu-icon"></i>库位
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('suppliers.*')) active @endif" href="{{ route('shop.suppliers.index') }}">
                            <i class="fa-solid fa-truck-field sidebar-menu-icon"></i>供应商
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('customers.*')) active @endif" href="{{ route('shop.customers.index') }}">
                            <i class="fa-solid fa-users sidebar-menu-icon"></i>客户
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('price-lists.*')) active @endif" href="{{ route('shop.price-lists.index') }}">
                            <i class="fa-solid fa-tags sidebar-menu-icon"></i>价格表
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('sequences.*')) active @endif" href="{{ route('shop.sequences.index') }}">
                            <i class="fa-solid fa-hashtag sidebar-menu-icon"></i>编号规则
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('products.labels.*')) active @endif" href="{{ route('shop.products.labels.index') }}">
                            <i class="fa-solid fa-barcode sidebar-menu-icon"></i>标签打印
                        </a>
                    </li>
                </ul>
            </li>

            {{-- 采购管理 --}}
            <li class="nav-item nav-group">
                <a class="nav-link nav-group-toggle" href="#sub-purchase"
                   data-bs-toggle="collapse" data-bs-target="#sub-purchase" data-bs-parent="#sidebar-nav"
                   aria-expanded="{{ $gPurchase ? 'true' : 'false' }}">
                    <i class="fa-solid fa-truck sidebar-menu-icon"></i>采购管理
                    <span class="menu-badge @if ($badges['pending_receive'] === 0) is-zero @endif" aria-label="待收货 {{ $badges['pending_receive'] }} 单">{{ $badges['pending_receive'] }}</span>
                    <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
                </a>
                <ul class="collapse @if($gPurchase)show @endif nav flex-column gap-1 ps-4 list-unstyled"
                    id="sub-purchase" data-bs-parent="#sidebar-nav">
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('purchase-orders.*')) active @endif" href="{{ route('shop.purchase-orders.index') }}">
                            <i class="fa-solid fa-file-invoice sidebar-menu-icon"></i>采购订单
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('receipts.*')) active @endif" href="{{ route('shop.receipts.index') }}">
                            <i class="fa-solid fa-dolly sidebar-menu-icon"></i>收货入库
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('purchase-returns.*')) active @endif" href="{{ route('shop.purchase-returns.index') }}">
                            <i class="fa-solid fa-rotate-left sidebar-menu-icon"></i>采购退货
                        </a>
                    </li>
                </ul>
            </li>

            {{-- 仓储管理 --}}
            <li class="nav-item nav-group">
                <a class="nav-link nav-group-toggle" href="#sub-stock"
                   data-bs-toggle="collapse" data-bs-target="#sub-stock" data-bs-parent="#sidebar-nav"
                   aria-expanded="{{ $gStock ? 'true' : 'false' }}">
                    <i class="fa-solid fa-warehouse sidebar-menu-icon"></i>仓储管理
                    <span class="menu-badge @if ($badges['low_stock'] === 0) is-zero @endif" aria-label="低库存 {{ $badges['low_stock'] }} 个商品">{{ $badges['low_stock'] }}</span>
                    <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
                </a>
                <ul class="collapse @if($gStock)show @endif nav flex-column gap-1 ps-4 list-unstyled"
                    id="sub-stock" data-bs-parent="#sidebar-nav">
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('movements.*')) active @endif" href="{{ route('shop.movements.index') }}">
                            <i class="fa-solid fa-arrow-right-arrow-left sidebar-menu-icon"></i>库存流水
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('warehouse.putaway.*')) active @endif" href="{{ route('shop.warehouse.putaway.index') }}">
                            <i class="fa-solid fa-box-open sidebar-menu-icon"></i>上架调拨
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('counts.*')) active @endif" href="{{ route('shop.counts.index') }}">
                            <i class="fa-solid fa-clipboard-check sidebar-menu-icon"></i>盘点单
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('scraps.*')) active @endif" href="{{ route('shop.scraps.index') }}">
                            <i class="fa-solid fa-trash-can sidebar-menu-icon"></i>报废单
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('lots.*')) active @endif" href="{{ route('shop.lots.index') }}">
                            <i class="fa-solid fa-hourglass-half sidebar-menu-icon"></i>批次效期预警
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('serials.*')) active @endif" href="{{ route('shop.serials.index') }}">
                            <i class="fa-solid fa-barcode sidebar-menu-icon"></i>序列号台账
                        </a>
                    </li>
                </ul>
            </li>

            {{-- 销售管理 --}}
            <li class="nav-item nav-group">
                <a class="nav-link nav-group-toggle" href="#sub-sales"
                   data-bs-toggle="collapse" data-bs-target="#sub-sales" data-bs-parent="#sidebar-nav"
                   aria-expanded="{{ $gSales ? 'true' : 'false' }}">
                    <i class="fa-solid fa-cart-shopping sidebar-menu-icon"></i>销售管理
                    <span class="menu-badge @if ($badges['pending_ship'] === 0) is-zero @endif" aria-label="待发货 {{ $badges['pending_ship'] }} 单">{{ $badges['pending_ship'] }}</span>
                    <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
                </a>
                <ul class="collapse @if($gSales)show @endif nav flex-column gap-1 ps-4 list-unstyled"
                    id="sub-sales" data-bs-parent="#sidebar-nav">
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('sales-returns.*')) active @endif" href="{{ route('shop.sales-returns.index') }}">
                            <i class="fa-solid fa-rotate-left sidebar-menu-icon"></i>销售退货
                        </a>
                    </li>
                </ul>
            </li>

            <li class="nav-item">
                <a class="nav-link @if(Route::is('pos.index') || Route::is('pos.cart.*') || Route::is('pos.saved.*') || Route::is('pos.checkout') || Route::is('pos.search') || Route::is('pos.payment-methods')) active @endif" href="{{ route('shop.pos.index') }}">
                    <i class="fa-solid fa-cash-register sidebar-menu-icon"></i>POS 收银
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if(Route::is('pos.orders.*')) active @endif" href="{{ route('shop.pos.orders.index') }}">
                    <i class="fa-solid fa-receipt sidebar-menu-icon"></i>POS 订单记录
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if(Route::is('pos.stations.*')) active @endif" href="{{ route('shop.pos.stations.index') }}">
                    <i class="fa-solid fa-desktop sidebar-menu-icon"></i>收银台配置
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link @if(Route::is('pos.receipt-template.*')) active @endif" href="{{ route('shop.pos.receipt-template.index') }}">
                    <i class="fa-solid fa-scroll sidebar-menu-icon"></i>小票模板
                </a>
            </li>

            {{-- 财务会计 --}}
            <li class="nav-item nav-group">
                <a class="nav-link nav-group-toggle" href="#sub-finance"
                   data-bs-toggle="collapse" data-bs-target="#sub-finance" data-bs-parent="#sidebar-nav"
                   aria-expanded="{{ $gFinance ? 'true' : 'false' }}">
                    <i class="fa-solid fa-book-open sidebar-menu-icon"></i>财务会计
                    <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
                </a>
                <ul class="collapse @if($gFinance)show @endif nav flex-column gap-1 ps-4 list-unstyled"
                    id="sub-finance" data-bs-parent="#sidebar-nav">
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('statements.*')) active @endif" href="{{ route('shop.statements.index') }}">
                            <i class="fa-solid fa-handshake-angle sidebar-menu-icon"></i>{{ Route::is('statements.*') && request('type') === 'supplier' ? '供应商对账' : '客户对账' }}
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('reconciliations.*')) active @endif" href="{{ route('shop.reconciliations.index') }}">
                            <i class="fa-solid fa-building-columns sidebar-menu-icon"></i>银行对账
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('tax-reports.*')) active @endif" href="{{ route('shop.tax-reports.index') }}">
                            <i class="fa-solid fa-percent sidebar-menu-icon"></i>税费归集
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('closing.*')) active @endif" href="{{ route('shop.closing.index') }}">
                            <i class="fa-solid fa-lock sidebar-menu-icon"></i>期末结账
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('expenses.*')) active @endif" href="{{ route('shop.expenses.index') }}">
                            <i class="fa-solid fa-receipt sidebar-menu-icon"></i>费用报销
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('aging.*')) active @endif" href="{{ route('shop.aging.index') }}">
                            <i class="fa-solid fa-clock sidebar-menu-icon"></i>账龄表
                        </a>
                    </li>
                </ul>
            </li>

            {{-- 报表中心 --}}
            <li class="nav-item nav-group">
                <a class="nav-link nav-group-toggle" href="#sub-report"
                   data-bs-toggle="collapse" data-bs-target="#sub-report" data-bs-parent="#sidebar-nav"
                   aria-expanded="{{ $gReport ? 'true' : 'false' }}">
                    <i class="fa-solid fa-chart-line sidebar-menu-icon"></i>报表中心
                    <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
                </a>
                <ul class="collapse @if($gReport)show @endif nav flex-column gap-1 ps-4 list-unstyled"
                    id="sub-report" data-bs-parent="#sidebar-nav">
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('financial-reports.*')) active @endif" href="{{ route('shop.financial-reports.index') }}">
                            <i class="fa-solid fa-file-invoice-dollar sidebar-menu-icon"></i>三大财务报表
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('profit-stock.*')) active @endif" href="{{ route('shop.profit-stock.index') }}">
                            <i class="fa-solid fa-chart-pie sidebar-menu-icon"></i>毛利与存货估值
                        </a>
                    </li>
                </ul>
            </li>

            {{-- 系统设置 --}}
            <li class="nav-item nav-group">
                <a class="nav-link nav-group-toggle" href="#sub-setting"
                   data-bs-toggle="collapse" data-bs-target="#sub-setting" data-bs-parent="#sidebar-nav"
                   aria-expanded="{{ $gSetting ? 'true' : 'false' }}">
                    <i class="fa-solid fa-gear sidebar-menu-icon"></i>系统设置
                    <i class="fa-solid fa-chevron-down sidebar-chevron"></i>
                </a>
                <ul class="collapse @if($gSetting)show @endif nav flex-column gap-1 ps-4 list-unstyled"
                    id="sub-setting" data-bs-parent="#sidebar-nav">
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('automation.*')) active @endif" href="{{ route('shop.automation.index') }}">
                            <i class="fa-solid fa-heart-pulse sidebar-menu-icon"></i>自动化监控
                        </a>
                    </li>
                    @if(auth()->check() && auth()->user()->canManageAi())
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('ai.settings') || Route::is('ai.welcome')) active @endif" href="{{ route('shop.ai.settings') }}">
                            <i class="fa-solid fa-robot sidebar-menu-icon"></i>AI 设置
                        </a>
                    </li>
                    @endif
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('payment-gateways.*')) active @endif" href="{{ route('shop.payment-gateways.index') }}">
                            <i class="fa-solid fa-plug-circle-check sidebar-menu-icon"></i>支付接入引导
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('audit-logs.*')) active @endif" href="{{ route('shop.audit-logs.index') }}">
                            <i class="fa-solid fa-clipboard-list sidebar-menu-icon"></i>审计日志
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link @if(Route::is('backups.*')) active @endif" href="{{ route('shop.backups.index') }}">
                            <i class="fa-solid fa-database sidebar-menu-icon"></i>本地备份
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/shop/admin">
                            <i class="fa-solid fa-user-shield sidebar-menu-icon"></i>系统管理后台
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
</aside>
