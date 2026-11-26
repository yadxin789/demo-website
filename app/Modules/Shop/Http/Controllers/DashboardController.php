<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Modules\Shop\Services\DashboardService;
use App\Modules\Shop\Services\System\PaymentGatewayService;
use Illuminate\View\View;

/**
 * 仪表盘：数据聚合交给 DashboardService，控制器只负责渲染入口。
 */
class DashboardController extends Controller
{
    public function index(DashboardService $dashboard, PaymentGatewayService $paymentGateways): View
    {
        return view('shop::dashboard.index', $dashboard->build() + [
            'paymentGatewaySummary' => $paymentGateways->summary(),
        ]);
    }
}
