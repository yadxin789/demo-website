<?php

namespace App\Modules\Erp\Http\Controllers;

use App\Modules\Erp\Support\DashboardService;
use Illuminate\Support\Facades\Route;

/**
 * 工作台（S4）：指标由本岗配置决定，不再"一次算 10 个指标给所有人"。
 */
class HomeController extends Controller
{
    public function index(DashboardService $dashboard)
    {
        return view('erp::home', ['dashboard' => $dashboard->forCurrentUser()]);
    }

    /**
     * 经营驾驶舱（G14）：老板只读汇总，一张页面看全公司，不提供任何写操作。
     */
    public function exec(DashboardService $dashboard)
    {
        $keys = [
            'open_orders', 'open_pos', 'open_wos', 'pending_qc',
            'ar_total', 'ap_total', 'stock_qty', 'materials',
        ];
        $cards = $dashboard->cardsForKeys($keys);
        $charts = $dashboard->chartsForKeys($keys);

        $readonlyReports = collect([
            ['name' => '销售分析', 'route' => 'erp.report.sales'],
            ['name' => '质量报表', 'route' => 'erp.report.quality'],
            ['name' => '供应商绩效', 'route' => 'erp.report.supplier'],
            ['name' => '财务报表', 'route' => 'erp.report.finance'],
            ['name' => '报表中心', 'route' => 'erp.report.center'],
        ])->filter(fn ($item) => Route::has($item['route']))->values();

        return view('erp::dashboard.exec', compact('cards', 'readonlyReports', 'charts'));
    }
}
