<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Services\DashboardService;
use Illuminate\View\View;

/**
 * 人力看板（H0 版：真实查询，空库也照常渲染）。
 *
 * 客户第一眼要看的是"有事要做"：待审请假、待处理考勤异常、待评加班、合同到期。
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    public function index(): View
    {
        return view('hr::dashboard', [
            'k' => $this->service->snapshot(),
        ]);
    }
}
