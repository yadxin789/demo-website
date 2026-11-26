<?php

namespace App\Modules\Shop\Http\Controllers\System;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Services\System\SystemStatusService;
use Illuminate\View\View;

class AutomationStatusController extends Controller
{
    public function __construct(protected SystemStatusService $status)
    {
    }

    public function index(): View
    {
        return view('shop::system.automation.index', $this->status->overview());
    }
}
