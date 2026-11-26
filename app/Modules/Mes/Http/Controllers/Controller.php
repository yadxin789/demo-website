<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

/**
 * MES 控制器基类。
 *
 * 只做一件事：把 Laravel 的路由控制器约定固定在一处，
 * 业务控制器不必各自 use 一遍 Illuminate\Routing\Controller（G-01 的同类收敛）。
 */
abstract class Controller extends BaseController
{
}
