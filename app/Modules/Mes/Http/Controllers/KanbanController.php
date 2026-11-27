<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Services\KanbanService;
use Illuminate\View\View;

/**
 * 车间看板（M6-02）：给 1920×1080 车间大屏用，30 秒自动刷新。
 *
 * 取数全部走 KanbanService，页面本身不写查询。
 */
class KanbanController extends Controller
{
    public function index(KanbanService $kanban): View
    {
        return view('mes::kanban.index', ['k' => $kanban->snapshot()]);
    }
}
