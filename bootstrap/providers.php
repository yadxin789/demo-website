<?php

declare(strict_types=1);

/*
| 装配顺序：Core 在前（视图命名空间），产品在后。
| 新增产品时必须在这里加一行，然后跑一次 php artisan route:list 确认路由已装。
*/
return [
    App\Providers\AppServiceProvider::class,
    App\Core\Providers\CoreServiceProvider::class,
    App\Modules\Erp\Providers\ErpServiceProvider::class,
    App\Modules\Pm\Providers\PmServiceProvider::class,
    App\Modules\Shop\Providers\ShopServiceProvider::class,
    App\Modules\Mes\Providers\MesServiceProvider::class,
    App\Modules\Hr\Providers\HrServiceProvider::class,
];