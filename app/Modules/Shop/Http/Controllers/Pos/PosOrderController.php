<?php

namespace App\Modules\Shop\Http\Controllers\Pos;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Services\Pos\ReceiptPrintService;
use Illuminate\View\View;

class PosOrderController extends Controller
{
    /**
     * 订单列表：超级管理员看全部，普通收银员仅看本人当班订单。
     */
    public function index(): View
    {
        $query = PosOrder::with(['cashier', 'station', 'customer'])
            ->withCount('lines')
            ->orderByDesc('id');

        $user = auth()->user();
        $isSuperAdmin = $user->roles()->where('code', 'super-admin')->exists();
        if (! $isSuperAdmin) {
            $query->where('cashier_id', $user->id);
        }

        $orders = $query->paginate(20);

        return view('shop::pos.orders.index', compact('orders'));
    }

    public function show(PosOrder $posOrder): View
    {
        $this->authorize('viewOrder', $posOrder);

        $posOrder->load(['lines.product', 'payments.method', 'cashier', 'station', 'customer']);

        return view('shop::pos.orders.show', ['order' => $posOrder]);
    }

    /**
     * 浏览器备选小票：本地打印代理不可用时，用可打印 Blade 模板独立打印。
     */
    public function receipt(PosOrder $posOrder, ReceiptPrintService $printer): View
    {
        $this->authorize('viewOrder', $posOrder);

        $posOrder->load(['lines.product', 'payments.method', 'cashier', 'station', 'customer']);
        $data = $printer->receiptData($posOrder);

        return view('shop::pos.receipt.print', [
            'order' => $posOrder,
            'data' => $data,
        ]);
    }
}
