<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Models\FinishedReceipt;
use App\Modules\Mes\Models\WorkOrder;
use App\Modules\Mes\Services\CompletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 完工入库（MES，M7 增补）。
 *
 * 列表看已入库批次，入库动作从工单详情发起（也支持在这个页面直接给选定工单入库）。
 */
class FinishedReceiptController extends Controller
{
    public function __construct(private readonly CompletionService $service)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $receipts = FinishedReceipt::query()
            ->with(['workOrder:id,order_no,status', 'material:id,code,name,unit', 'receiver:id,name'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('lot_no', 'like', "%{$q}%")
                        ->orWhere('receipt_no', 'like', "%{$q}%")
                        ->orWhereHas('workOrder', fn ($wo) => $wo->where('order_no', 'like', "%{$q}%"))
                        ->orWhereHas('material', fn ($m) => $m->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
                });
            })
            ->latest('received_at')
            ->paginate(15)
            ->withQueryString();

        $openOrders = WorkOrder::query()
            ->with('product:id,code,name,unit')
            ->whereIn('status', ['released', 'in_progress', 'completed'])
            ->orderByRaw("case status when 'in_progress' then 0 when 'released' then 1 else 2 end")
            ->orderBy('plan_end')
            ->get();

        return view('mes::receipts.index', [
            'receipts' => $receipts,
            'q' => $q,
            'openOrders' => $openOrders,
            'stat' => [
                'today_qty' => (float) FinishedReceipt::query()->whereDate('received_at', now()->toDateString())->sum('qty'),
                'month_qty' => (float) FinishedReceipt::query()->whereMonth('received_at', now()->month)->whereYear('received_at', now()->year)->sum('qty'),
                'lots' => FinishedReceipt::query()->distinct('lot_no')->count('lot_no'),
            ],
        ]);
    }

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'qty' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'warehouse' => ['nullable', 'string', 'max:32'],
            'lot_no' => ['nullable', 'string', 'max:32'],
            'remark' => ['nullable', 'string', 'max:200'],
        ], [], ['qty' => '入库数量', 'warehouse' => '入库仓', 'lot_no' => '批次号', 'remark' => '备注']);

        $result = $this->service->receive($workOrder, $data, auth('mes')->id());

        return redirect()
            ->route('mes.work-orders.show', $workOrder)
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function close(WorkOrder $workOrder): RedirectResponse
    {
        $result = $this->service->close($workOrder);

        return redirect()
            ->route('mes.work-orders.show', $workOrder)
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }
}
