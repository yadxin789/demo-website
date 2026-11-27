<?php

declare(strict_types=1);

use App\Modules\Shop\Http\Controllers\Auth\AuthController;
use App\Modules\Shop\Http\Controllers\AiController;
use App\Modules\Shop\Http\Controllers\AiDelegationController;
use App\Modules\Shop\Http\Controllers\DashboardController;
use App\Modules\Shop\Http\Controllers\Master\CategoryController;
use App\Modules\Shop\Http\Controllers\Master\CustomerController;
use App\Modules\Shop\Http\Controllers\Master\LocationController;
use App\Modules\Shop\Http\Controllers\Master\PaymentMethodController;
use App\Modules\Shop\Http\Controllers\Master\PriceListItemController;
use App\Modules\Shop\Http\Controllers\Master\PriceListController;
use App\Modules\Shop\Http\Controllers\Master\ProductController;
use App\Modules\Shop\Http\Controllers\Master\ProductImageController;
use App\Modules\Shop\Http\Controllers\Exchange\ExchangeController;
use App\Modules\Shop\Http\Controllers\Master\LabelController;
use App\Modules\Shop\Http\Controllers\Master\SequenceController;
use App\Modules\Shop\Http\Controllers\Master\SupplierController;
use App\Modules\Shop\Http\Controllers\Master\TaxController;
use App\Modules\Shop\Http\Controllers\Master\UnitController;
use App\Modules\Shop\Http\Controllers\Master\WarehouseController;
use App\Modules\Shop\Http\Controllers\NotificationController;
use App\Modules\Shop\Http\Controllers\LandingController;
use App\Modules\Shop\Http\Controllers\Pos\PosController;
use App\Modules\Shop\Http\Controllers\Pos\PosOrderController;
use App\Modules\Shop\Http\Controllers\Pos\PosStationController;
use App\Modules\Shop\Http\Controllers\Pos\ReceiptTemplateController;
use App\Modules\Shop\Http\Controllers\ProfileController;
use App\Modules\Shop\Http\Controllers\Purchase\PurchaseController;
use App\Modules\Shop\Http\Controllers\Purchase\ReceiptController;
use App\Modules\Shop\Http\Controllers\ScanController;
use App\Modules\Shop\Http\Controllers\Purchase\ReturnController;
use App\Modules\Shop\Http\Controllers\Sales\ReturnController as SalesReturnController;
use App\Modules\Shop\Http\Controllers\Finance\StatementController;
use App\Modules\Shop\Http\Controllers\Finance\ReconciliationController;
use App\Modules\Shop\Http\Controllers\Finance\TaxReportController;
use App\Modules\Shop\Http\Controllers\Finance\ClosingController;
use App\Modules\Shop\Http\Controllers\Finance\AgingController;
use App\Modules\Shop\Http\Controllers\Finance\ExpenseController;
use App\Modules\Shop\Http\Controllers\Finance\FinancialReportController;
use App\Modules\Shop\Http\Controllers\Report\ProfitStockController;
use App\Modules\Shop\Http\Controllers\System\AuditLogController;
use App\Modules\Shop\Http\Controllers\System\BackupController;
use App\Modules\Shop\Http\Controllers\System\PaymentGatewayController;
use App\Modules\Shop\Http\Controllers\Warehouse\CountController;
use App\Modules\Shop\Http\Controllers\Warehouse\ScrapController;
use App\Modules\Shop\Http\Controllers\Warehouse\LotController;
use App\Modules\Shop\Http\Controllers\Warehouse\MovementController;
use App\Modules\Shop\Http\Controllers\Warehouse\PutawayController;
use App\Modules\Shop\Http\Controllers\Warehouse\SerialController;
use App\Modules\Shop\Services\Exchange\MasterDataExchange;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| 百货零售 ERP 路由（S3 从 E:\erp-shop 搬入）
|--------------------------------------------------------------------------
| HC-07：必须同时具备 prefix('shop') 与 name('shop.')
| HC-08：中间件链固定为 web -> module:shop -> product.only:shop
| HC-09：不做旧 URL 兼容；原项目 / 是落地页，这里换成按登录态分流
| 认证：auth:shop / guest:shop；权限：shop.permission:<code>（Shop 自带 RBAC，不走 spatie）
*/

Route::middleware(['web', 'module:shop', 'product.only:shop', 'shop.audit'])
    ->prefix('shop')
    ->name('shop.')
    ->group(function () {

    // 产品根（S8）：未登录先看对外首页，已登录直接进工作台
    Route::get('/', [LandingController::class, 'show'])->name('root');

    Route::middleware('guest:shop')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    });

    Route::middleware('auth:shop')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('scan/resolve', [ScanController::class, 'resolve'])
            ->middleware('throttle:120,1')->name('scan.resolve');
        Route::get('/password', [AuthController::class, 'showChangePassword'])->name('password.show');
        Route::post('/password', [AuthController::class, 'changePassword'])->name('password.update');

        // 个人中心：基本资料 / 头像
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
        Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
        Route::get('/profile/avatar/image', [ProfileController::class, 'avatarImage'])->name('profile.avatar.image');

        // 站内消息：仅个人可见，不挂业务权限
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notification.index');
        Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notification.open');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notification.mark-read');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notification.mark-all-read');
        Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notification.destroy');

        // ==================== AI 智能助手 ====================
        Route::get('/ai', [AiController::class, 'welcome'])->name('ai.welcome');
        Route::get('/ai/notice', [AiController::class, 'notice'])->name('ai.notice');
        Route::get('/ai/chat', [AiController::class, 'chat'])->name('ai.chat');
        Route::post('/ai/ask', [AiController::class, 'ask'])->name('ai.ask');
        Route::post('/ai/clear', [AiController::class, 'clear'])->name('ai.clear');
        Route::post('/ai/conversations', [AiController::class, 'storeConversation'])->name('ai.conversations.store');
        Route::patch('/ai/conversations/{conversation}', [AiController::class, 'renameConversation'])->name('ai.conversations.rename')->whereNumber('conversation');
        Route::delete('/ai/conversations/{conversation}', [AiController::class, 'destroyConversation'])->name('ai.conversations.destroy')->whereNumber('conversation');
        Route::post('/ai/conversations/{conversation}/clear', [AiController::class, 'clearConversation'])->name('ai.conversations.clear')->whereNumber('conversation');
        Route::get('/ai/conversations/{conversation}/export', [AiController::class, 'exportConversation'])->name('ai.conversations.export')->whereNumber('conversation');
        Route::get('/ai/attachments/{attachment}/preview', [AiController::class, 'previewAttachment'])->name('ai.attachments.preview')->whereNumber('attachment');
        Route::get('/ai/attachments/{attachment}/download', [AiController::class, 'downloadAttachment'])->name('ai.attachments.download')->whereNumber('attachment');
        Route::get('/ai/docs', [AiController::class, 'docs'])->name('ai.docs');
        Route::post('/ai/docs/generate', [AiController::class, 'generateDoc'])->name('ai.docs.generate');

        // AI 授权中心：令牌签发 + 变更提案确认
        Route::get('/ai-delegation', [AiDelegationController::class, 'index'])->name('ai-delegation.index');
        Route::post('/ai-delegation/tokens', [AiDelegationController::class, 'storeToken'])->name('ai-delegation.tokens.store');
        Route::delete('/ai-delegation/tokens/{token}', [AiDelegationController::class, 'revokeToken'])->name('ai-delegation.tokens.revoke');
        Route::post('/ai-delegation/proposals/{proposal}/confirm', [AiDelegationController::class, 'confirmProposal'])->name('ai-delegation.proposals.confirm');
        Route::post('/ai-delegation/proposals/{proposal}/reject', [AiDelegationController::class, 'rejectProposal'])->name('ai-delegation.proposals.reject');

        // AI 设置页：管理员 / system.ai.manage 权限
        Route::middleware('shop.permission:system.ai.manage')->group(function () {
            Route::get('/ai/settings', [AiController::class, 'settings'])->name('ai.settings');
            Route::put('/ai/settings', [AiController::class, 'updateSettings'])->name('ai.settings.update');
            Route::get('/ai/settings/test', [AiController::class, 'test'])->name('ai.settings.test');
            Route::post('/ai/settings/providers/{provider}', [AiController::class, 'saveProvider'])->name('ai.providers.save');
            Route::post('/ai/settings/providers/{provider}/toggle', [AiController::class, 'toggleProvider'])->name('ai.providers.toggle');
            Route::post('/ai/settings/providers/{provider}/default', [AiController::class, 'setDefaultProvider'])->name('ai.providers.default');
            Route::post('/ai/settings/providers/{provider}/test', [AiController::class, 'testProvider'])->name('ai.providers.test');
            Route::post('/ai/settings/providers/{provider}/sync-models', [AiController::class, 'syncProviderModels'])->name('ai.providers.sync');
            Route::delete('/ai/settings/providers/{provider}', [AiController::class, 'removeProvider'])->name('ai.providers.remove');
            Route::post('/ai/settings/models', [AiController::class, 'storeModel'])->name('ai.models.store');
            Route::put('/ai/settings/models/{model}', [AiController::class, 'updateModel'])->name('ai.models.update')->whereNumber('model');
            Route::post('/ai/settings/models/{model}/default', [AiController::class, 'setDefaultModel'])->name('ai.models.default')->whereNumber('model');
            Route::delete('/ai/settings/models/{model}', [AiController::class, 'destroyModel'])->name('ai.models.destroy')->whereNumber('model');
        });

        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->middleware('shop.permission:dashboard.view')->name('dashboard');

        // 基础资料
        Route::middleware('shop.permission:master.view')->group(function () {
            Route::resource('categories', CategoryController::class)->except('show');
            Route::resource('units', UnitController::class)->except('show');
            Route::resource('taxes', TaxController::class)->except('show');
            Route::resource('payment-methods', PaymentMethodController::class)->except('show');
            Route::resource('warehouses', WarehouseController::class)->except('show');
            Route::get('locations/labels', [LocationController::class, 'labels'])->name('locations.labels.index');
            Route::resource('locations', LocationController::class)->except('show');
            Route::resource('suppliers', SupplierController::class)->except('show');
            Route::resource('customers', CustomerController::class)->except('show');
            Route::resource('products', ProductController::class)->except('show');
            Route::get('products/{product}/image/{variant?}', [ProductImageController::class, 'show'])
                ->whereIn('variant', ['thumb', 'full'])->name('products.image');
            Route::delete('products/{product}/image', [ProductImageController::class, 'destroy'])->name('products.image.destroy');

            // 主数据 CSV 模板 / 导入 / 导出（type：products|customers|suppliers）
            Route::get('exchange/{type}/template', [ExchangeController::class, 'template'])
                ->whereIn('type', MasterDataExchange::TYPES)->name('exchange.template');
            Route::get('exchange/{type}/export', [ExchangeController::class, 'export'])
                ->whereIn('type', MasterDataExchange::TYPES)->name('exchange.export');
            Route::get('exchange/{type}/import', [ExchangeController::class, 'show'])
                ->whereIn('type', MasterDataExchange::TYPES)->name('exchange.import');
            Route::post('exchange/{type}/import', [ExchangeController::class, 'store'])
                ->whereIn('type', MasterDataExchange::TYPES)->name('exchange.import.store');
            Route::get('products/labels', [LabelController::class, 'index'])->name('products.labels.index');
            Route::resource('price-lists', PriceListController::class)->except('show');
            Route::post('price-lists/{price_list}/items', [PriceListItemController::class, 'store'])->name('price-lists.items.store');
            Route::get('price-lists/{price_list}/items/{item}/edit', [PriceListItemController::class, 'edit'])->name('price-lists.items.edit');
            Route::match(['put', 'patch'], 'price-lists/{price_list}/items/{item}', [PriceListItemController::class, 'update'])->name('price-lists.items.update');
            Route::delete('price-lists/{price_list}/items/{item}', [PriceListItemController::class, 'destroy'])->name('price-lists.items.destroy');
            Route::get('sequences', [SequenceController::class, 'index'])->name('sequences.index');        Route::get('sequences/{sequence}/edit', [SequenceController::class, 'edit'])->name('sequences.edit');
            Route::patch('sequences/{sequence}', [SequenceController::class, 'update'])->name('sequences.update');
        });

        // 采购
        Route::middleware('shop.permission:purchase.view')->group(function () {
            Route::get('purchase-orders', [PurchaseController::class, 'index'])->name('purchase-orders.index');
            Route::get('purchase-orders/create', [PurchaseController::class, 'create'])->name('purchase-orders.create');
            Route::post('purchase-orders', [PurchaseController::class, 'store'])->name('purchase-orders.store');
            Route::get('purchase-orders/{purchaseOrder}', [PurchaseController::class, 'show'])->name('purchase-orders.show');
            Route::get('purchase-orders/{purchaseOrder}/edit', [PurchaseController::class, 'edit'])->name('purchase-orders.edit');
            Route::match(['put', 'patch'], 'purchase-orders/{purchaseOrder}', [PurchaseController::class, 'update'])->name('purchase-orders.update');
            Route::delete('purchase-orders/{purchaseOrder}', [PurchaseController::class, 'destroy'])->name('purchase-orders.destroy');
            Route::post('purchase-orders/{purchaseOrder}/transition', [PurchaseController::class, 'transition'])->name('purchase-orders.transition');
            Route::get('receipts', [ReceiptController::class, 'index'])->name('receipts.index');
            Route::get('receipts/create', [ReceiptController::class, 'create'])->name('receipts.create');
            Route::post('receipts', [ReceiptController::class, 'store'])->name('receipts.store');
            Route::get('receipts/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
            Route::get('purchase-returns', [ReturnController::class, 'index'])->name('purchase-returns.index');
            Route::get('purchase-returns/create', [ReturnController::class, 'create'])->name('purchase-returns.create');
            Route::post('purchase-returns', [ReturnController::class, 'store'])->name('purchase-returns.store');
            Route::get('purchase-returns/{purchaseReturn}', [ReturnController::class, 'show'])->name('purchase-returns.show');
            Route::post('purchase-returns/{purchaseReturn}/post', [ReturnController::class, 'post'])->name('purchase-returns.post');
        });

        // 销售退货
        Route::middleware('shop.permission:sales.view')->group(function () {
            Route::get('sales-returns', [SalesReturnController::class, 'index'])->name('sales-returns.index');
            Route::get('sales-returns/create', [SalesReturnController::class, 'create'])->name('sales-returns.create');
            Route::post('sales-returns', [SalesReturnController::class, 'store'])->name('sales-returns.store');
            Route::get('sales-returns/{salesReturn}', [SalesReturnController::class, 'show'])->name('sales-returns.show');
            Route::post('sales-returns/{salesReturn}/post', [SalesReturnController::class, 'post'])->name('sales-returns.post');
        });

        // POS 收银
        Route::middleware('shop.permission:pos.view')->group(function () {
            Route::get('pos', [PosController::class, 'index'])->name('pos.index');
            Route::get('pos/search', [PosController::class, 'search'])->name('pos.search');
            Route::post('pos/cart', [PosController::class, 'addLine'])->name('pos.cart.add');
            Route::get('pos/trace/lots', [PosController::class, 'lots'])->name('pos.trace.lots');
            Route::get('pos/trace/serials', [PosController::class, 'serials'])->name('pos.trace.serials');
            Route::get('pos/customers', [PosController::class, 'customers'])->name('pos.customers');
            Route::post('pos/cart/qty', [PosController::class, 'updateQty'])->name('pos.cart.qty');
            Route::post('pos/cart/discount', [PosController::class, 'applyDiscount'])->name('pos.cart.discount');
            Route::post('pos/cart/order-discount', [PosController::class, 'applyOrderDiscount'])->name('pos.cart.order-discount');
            Route::get('pos/payment-methods', [PosController::class, 'paymentMethods'])->name('pos.payment-methods');
            Route::post('pos/payment-qr', [PosController::class, 'paymentQr'])->name('pos.payment-qr');
            Route::get('pos/session/summary', [PosController::class, 'shiftSummary'])->name('pos.session.summary');
            Route::post('pos/session/close', [PosController::class, 'closeShift'])->name('pos.session.close');
            Route::post('pos/checkout', [PosController::class, 'checkout'])->name('pos.checkout');
            Route::get('pos/orders', [PosOrderController::class, 'index'])->name('pos.orders.index');
            Route::get('pos/orders/{posOrder}', [PosOrderController::class, 'show'])->name('pos.orders.show');
            Route::get('pos/orders/{posOrder}/receipt', [PosOrderController::class, 'receipt'])->name('pos.orders.receipt');
            Route::get('pos/stations', [PosStationController::class, 'index'])->name('pos.stations.index');
            Route::get('pos/stations/create', [PosStationController::class, 'create'])->name('pos.stations.create');
            Route::post('pos/stations', [PosStationController::class, 'store'])->name('pos.stations.store');
            Route::get('pos/stations/{station}/edit', [PosStationController::class, 'edit'])->name('pos.stations.edit');
            Route::match(['put', 'patch'], 'pos/stations/{station}', [PosStationController::class, 'update'])->name('pos.stations.update');
            Route::post('pos/stations/{station}/test-print', [PosStationController::class, 'testPrint'])->name('pos.stations.test-print');
            Route::post('pos/stations/{station}/test-drawer', [PosStationController::class, 'testDrawer'])->name('pos.stations.test-drawer');
            Route::get('pos/receipt-template', [ReceiptTemplateController::class, 'index'])->name('pos.receipt-template.index');
            Route::post('pos/receipt-template', [ReceiptTemplateController::class, 'update'])->name('pos.receipt-template.update');
            Route::get('pos/saved', [PosController::class, 'listSaved'])->name('pos.saved.index');
            Route::post('pos/saved', [PosController::class, 'saveSaved'])->name('pos.saved.save');
            Route::post('pos/saved/{id}/restore', [PosController::class, 'restoreSaved'])->name('pos.saved.restore');
            Route::delete('pos/saved/{id}', [PosController::class, 'deleteSaved'])->name('pos.saved.delete');
            Route::post('pos/cart/remove', [PosController::class, 'removeLine'])->name('pos.cart.remove');
            Route::middleware('shop.permission:pos.price.adjust')
                ->post('pos/cart/price', [PosController::class, 'updatePrice'])
                ->name('pos.cart.price');
        });

        // 财务会计
        Route::middleware('shop.permission:finance.view')->group(function () {
            Route::get('statements', [StatementController::class, 'index'])->name('statements.index');
            Route::get('reconciliations', [ReconciliationController::class, 'index'])->name('reconciliations.index');
            Route::post('reconciliations', [ReconciliationController::class, 'store'])->name('reconciliations.store');
            Route::post('reconciliations/import', [ReconciliationController::class, 'import'])->name('reconciliations.import');
            Route::post('reconciliations/match', [ReconciliationController::class, 'match'])->name('reconciliations.match');
            Route::post('reconciliations/{id}/unmatch', [ReconciliationController::class, 'unmatch'])->name('reconciliations.unmatch');
            Route::get('tax-reports', [TaxReportController::class, 'index'])->name('tax-reports.index');
            Route::get('closing', [ClosingController::class, 'index'])->name('closing.index');
            Route::post('closing/{id}/close', [ClosingController::class, 'close'])->name('closing.close');
            Route::get('aging', [AgingController::class, 'index'])->name('aging.index');
            Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::post('expenses/{id}/confirm', [ExpenseController::class, 'confirm'])->name('expenses.confirm');
            Route::post('expenses/{id}/pay', [ExpenseController::class, 'pay'])->name('expenses.pay');
            Route::post('expenses/{id}/void', [ExpenseController::class, 'void'])->name('expenses.void');
        });

        // 报表中心
        Route::middleware('shop.permission:report.view')->group(function () {
            Route::get('profit-stock', [ProfitStockController::class, 'index'])->name('profit-stock.index');
            Route::get('profit-stock/export', [ProfitStockController::class, 'export'])->name('profit-stock.export');
            Route::get('financial-reports', [FinancialReportController::class, 'index'])->name('financial-reports.index');
        });

        // 系统：审计日志（只读）
        Route::middleware('shop.permission:system.view')->group(function () {
            Route::get('automation', [\App\Modules\Shop\Http\Controllers\System\AutomationStatusController::class, 'index'])->name('automation.index');
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
            Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');

            // 本地备份：查看归 system.view，创建/还原属破坏性操作，额外要求系统管理员权限
            Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
            Route::middleware('shop.permission:system.user.manage')->group(function () {
                Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
                Route::post('backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
            });

            // 微信支付 / 支付宝接入引导：查看归 system.view，保存与检测需系统管理员
            Route::get('payment-gateways', [PaymentGatewayController::class, 'index'])->name('payment-gateways.index');
            Route::middleware('shop.permission:system.user.manage')->group(function () {
                Route::put('payment-gateways/{provider}', [PaymentGatewayController::class, 'update'])
                    ->name('payment-gateways.update');
                Route::post('payment-gateways/{provider}/check', [PaymentGatewayController::class, 'check'])
                    ->name('payment-gateways.check');
            });
        });

        // 仓储
        Route::middleware('shop.permission:warehouse.view')->group(function () {
            Route::get('movements', [MovementController::class, 'index'])->name('movements.index');
            Route::get('putaway', [PutawayController::class, 'index'])->name('warehouse.putaway.index');
            Route::post('putaway', [PutawayController::class, 'store'])->name('warehouse.putaway.store');
            Route::get('lots', [LotController::class, 'index'])->name('lots.index');
            Route::get('serials', [SerialController::class, 'index'])->name('serials.index');
            Route::get('counts', [CountController::class, 'index'])->name('counts.index');
            Route::get('counts/create', [CountController::class, 'create'])->name('counts.create');
            Route::post('counts', [CountController::class, 'store'])->name('counts.store');
            Route::get('counts/{count}', [CountController::class, 'show'])->name('counts.show');
            Route::post('counts/{count}/enter', [CountController::class, 'enter'])->name('counts.enter');
            Route::post('counts/{count}/scan', [CountController::class, 'scan'])->name('counts.scan');
            Route::post('counts/{count}/post', [CountController::class, 'post'])->name('counts.post');
            Route::get('scraps', [ScrapController::class, 'index'])->name('scraps.index');
            Route::get('scraps/create', [ScrapController::class, 'create'])->name('scraps.create');
            Route::post('scraps', [ScrapController::class, 'store'])->name('scraps.store');
            Route::get('scraps/{scrap}', [ScrapController::class, 'show'])->name('scraps.show');
            Route::post('scraps/{scrap}/post', [ScrapController::class, 'post'])->name('scraps.post');
        });
    });


});
