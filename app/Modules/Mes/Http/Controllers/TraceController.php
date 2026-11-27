<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Models\Lot;
use App\Modules\Mes\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 批次追溯（MES，M7 增补）。
 *
 * 输入批次号 / 工单号 / 物料，列出批次台账；
 * 点进某个批次看"上游（这批由哪些料组成）→ 本批 → 下游（这批料做成了哪些成品）"。
 */
class TraceController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $lots = Lot::query()
            ->with(['material:id,code,name,unit', 'workOrder:id,order_no'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('lot_no', 'like', "%{$q}%")
                        ->orWhereHas('workOrder', fn ($wo) => $wo->where('order_no', 'like', "%{$q}%"))
                        ->orWhereHas('material', fn ($m) => $m->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%"));
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('mes::trace.index', [
            'lots' => $lots,
            'q' => $q,
            'stat' => [
                'total' => Lot::query()->count(),
                'production' => Lot::query()->where('source', 'production')->count(),
                'linked' => Lot::query()->whereNotNull('parent_lot_no')->count(),
            ],
        ]);
    }

    public function show(Lot $lot): View
    {
        // 上游：顺着 parent_lot_no 往上找，最多 5 层，防环
        $upstream = [];
        $cursor = $lot;
        $seen = [$lot->lot_no => true];
        for ($i = 0; $i < 5 && $cursor->parent_lot_no; $i++) {
            $parent = Lot::query()->with('material:id,code,name,unit')->where('lot_no', $cursor->parent_lot_no)->first();
            if (! $parent || isset($seen[$parent->lot_no])) {
                break;
            }
            $seen[$parent->lot_no] = true;
            $upstream[] = $parent;
            $cursor = $parent;
        }

        $downstream = Lot::query()
            ->with(['material:id,code,name,unit', 'workOrder:id,order_no'])
            ->where('parent_lot_no', $lot->lot_no)
            ->orderByDesc('id')
            ->get();

        $receipts = \App\Modules\Mes\Models\FinishedReceipt::query()
            ->with('workOrder:id,order_no')
            ->where('lot_no', $lot->lot_no)
            ->latest('received_at')
            ->get();

        $order = $lot->workOrder;

        return view('mes::trace.show', [
            'lot' => $lot->load('material:id,code,name,unit'),
            'upstream' => $upstream,
            'downstream' => $downstream,
            'receipts' => $receipts,
            'order' => $order,
        ]);
    }
}
