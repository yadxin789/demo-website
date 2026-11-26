<?php

declare(strict_types=1);

namespace App\Modules\Shop\Providers;

use App\Modules\Shop\Http\Middleware\AuditContext;
use App\Modules\Shop\Http\Middleware\AuthorizePermission;
use App\Modules\Shop\Models\Customer;
use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\PosSession;
use App\Modules\Shop\Models\PriceList;
use App\Modules\Shop\Models\PriceListItem;
use App\Modules\Shop\Models\Product;
use App\Modules\Shop\Models\ProductCategory;
use App\Modules\Shop\Models\Supplier;
use App\Modules\Shop\Models\Tax;
use App\Modules\Shop\Models\Unit;
use App\Modules\Shop\Models\Warehouse;
use App\Modules\Shop\Observers\LocationObserver;
use App\Modules\Shop\Observers\PartnerObserver;
use App\Modules\Shop\Observers\PriceListObserver;
use App\Modules\Shop\Observers\ProductObserver;
use App\Modules\Shop\Observers\TaxonomyObserver;
use App\Modules\Shop\Policies\PosOrderPolicy;
use App\Modules\Shop\Policies\PosSessionPolicy;
use App\Modules\Shop\Services\Accounting\BillService;
use App\Modules\Shop\Services\Accounting\EntryService;
use App\Modules\Shop\Services\Accounting\PaymentService;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Inventory\CountService;
use App\Modules\Shop\Services\Inventory\InboundService;
use App\Modules\Shop\Services\Inventory\InventoryAuditor;
use App\Modules\Shop\Services\Inventory\OutboundService;
use App\Modules\Shop\Services\Inventory\ReservationService;
use App\Modules\Shop\Services\Inventory\StockService;
use App\Modules\Shop\Services\Inventory\TransferService;
use App\Modules\Shop\Services\Pos\CartService;
use App\Modules\Shop\Services\Pos\CheckoutService;
use App\Modules\Shop\Services\Pos\ReceiptPrintService;
use App\Modules\Shop\Services\Pos\ReceiptTemplateService;
use App\Modules\Shop\Services\Pos\SavedOrderService;
use App\Modules\Shop\Services\Pos\SessionService;
use App\Modules\Shop\Services\Pos\ShiftService;
use App\Modules\Shop\Services\Purchase\PurchaseService;
use App\Modules\Shop\Services\Purchase\ReceiptService;
use App\Modules\Shop\Services\Sales\InvoiceService;
use App\Modules\Shop\Services\Sales\SalesService;
use App\Modules\Shop\Services\Sales\ShipService;
use App\Modules\Shop\Services\Scan\ScanResolver;
use App\Modules\Shop\Support\SequenceGenerator;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * 百货零售 ERP 模块装配器。
 *
 * HC-07：路由由这里 loadRoutesFrom 载入，文件内部自带 prefix('shop') + name('shop.')
 * HC-19：视图命名空间 shop::
 * HC-17：刻意不 loadMigrationsFrom —— 迁移只用
 *        php artisan migrate --database=shop --path=app/Modules/Shop/Database/Migrations 显式执行，
 *        否则 `php artisan migrate` 会把五产品的迁移都指向默认连接。
 *
 * 与 ERP 的关键差异：Shop 自带 roles / permissions 表与 AuthorizePermission 中间件，
 * 不使用 spatie；所以路由里的权限中间件叫 shop.permission:<code>，不占用全局 permission 别名。
 */
class ShopServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 演示站是 SQLite，备份/还原用 PDO 版执行器；线上 MySQL 场景换回 MysqlDumper 即可
        $this->app->singleton(
            \App\Modules\Shop\Services\System\DatabaseDumper::class,
            fn () => new \App\Modules\Shop\Services\System\SqliteDumper()
        );

        $this->app->singleton(AuditLogger::class, fn () => new AuditLogger());
        $this->app->singleton(SequenceGenerator::class, fn () => new SequenceGenerator());
        $this->app->singleton(EntryService::class, fn () => new EntryService(
            app(SequenceGenerator::class),
            app(AuditLogger::class)
        ));
        $this->app->singleton(BillService::class, fn () => new BillService(
            app(SequenceGenerator::class),
            app(EntryService::class),
            app(AuditLogger::class)
        ));
        $this->app->singleton(PaymentService::class, fn () => new PaymentService(
            app(SequenceGenerator::class),
            app(EntryService::class),
            app(AuditLogger::class)
        ));
        $this->app->singleton(StockService::class, fn () => new StockService());
        $this->app->singleton(InventoryAuditor::class, fn () => new InventoryAuditor(app(AuditLogger::class)));
        $this->app->singleton(InboundService::class, fn () => new InboundService(app(StockService::class), app(InventoryAuditor::class)));
        $this->app->singleton(OutboundService::class, fn () => new OutboundService(app(StockService::class), app(InventoryAuditor::class)));
        $this->app->singleton(TransferService::class, fn () => new TransferService(app(StockService::class), app(InventoryAuditor::class)));
        $this->app->singleton(PurchaseService::class, fn () => new PurchaseService(
            app(SequenceGenerator::class),
            app(AuditLogger::class)
        ));
        $this->app->singleton(ReceiptService::class, fn () => new ReceiptService(
            app(SequenceGenerator::class),
            app(InboundService::class),
            app(EntryService::class),
            app(AuditLogger::class)
        ));
        $this->app->singleton(SalesService::class, fn () => new SalesService(
            app(SequenceGenerator::class),
            app(AuditLogger::class),
            app(ReservationService::class)
        ));
        $this->app->singleton(SessionService::class, fn () => new SessionService(app(AuditLogger::class)));
        $this->app->singleton(ShiftService::class, fn () => new ShiftService(app(AuditLogger::class)));
        $this->app->singleton(ReceiptPrintService::class, fn () => new ReceiptPrintService(
            app(Factory::class),
            app(ReceiptTemplateService::class),
        ));
        $this->app->singleton(CartService::class, fn () => new CartService(app(AuditLogger::class)));
        $this->app->singleton(SavedOrderService::class, fn () => new SavedOrderService(app(AuditLogger::class)));
        $this->app->singleton(CheckoutService::class, fn () => new CheckoutService(
            app(SequenceGenerator::class),
            app(CartService::class),
            app(AuditLogger::class),
            app(OutboundService::class),
            app(EntryService::class)
        ));
        $this->app->singleton(InvoiceService::class, fn () => new InvoiceService(
            app(SequenceGenerator::class),
            app(EntryService::class),
            app(AuditLogger::class)
        ));
        $this->app->singleton(ShipService::class, fn () => new ShipService(
            app(OutboundService::class),
            app(EntryService::class),
            app(AuditLogger::class),
            app(ReservationService::class)
        ));
        $this->app->singleton(CountService::class, fn () => new CountService(
            app(SequenceGenerator::class),
            app(StockService::class),
            app(InventoryAuditor::class),
            app(AuditLogger::class),
            app(ScanResolver::class),
            app(EntryService::class)
        ));
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'shop');

        $router = $this->app['router'];
        $router->aliasMiddleware('shop.permission', AuthorizePermission::class);
        $router->aliasMiddleware('shop.audit', AuditContext::class);

        Gate::policy(PosOrder::class, PosOrderPolicy::class);
        Gate::policy(PosSession::class, PosSessionPolicy::class);

        Product::observe(ProductObserver::class);
        Supplier::observe(PartnerObserver::class);
        Customer::observe(PartnerObserver::class);
        Location::observe(LocationObserver::class);
        Warehouse::observe(LocationObserver::class);
        PriceList::observe(PriceListObserver::class);
        PriceListItem::observe(PriceListObserver::class);
        Tax::observe(TaxonomyObserver::class);
        Unit::observe(TaxonomyObserver::class);
        ProductCategory::observe(TaxonomyObserver::class);
    }
}
