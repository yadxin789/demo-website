<?php

namespace App\Modules\Shop\Http\Controllers\Finance;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Customer;
use App\Modules\Shop\Models\Supplier;
use App\Modules\Shop\Services\Finance\StatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class StatementController extends Controller
{
    public function __construct(protected StatementService $statements)
    {
    }

    public function index(Request $request): View
    {
        $type = $request->input('type') === 'supplier' ? 'supplier' : 'customer';
        $customerId = $type === 'customer' && $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
        $supplierId = $type === 'supplier' && $request->filled('supplier_id') ? (int) $request->input('supplier_id') : null;
        $start = $this->parseDate($request->input('start'));
        $end = $this->parseDate($request->input('end'));

        $result = $type === 'supplier'
            ? $this->statements->supplierStatement($supplierId, $start, $end)
            : $this->statements->statement($customerId, $start, $end);

        $customers = Customer::orderBy('code')->get();
        $suppliers = Supplier::orderBy('code')->get();

        return view('shop::finance.statements.index', array_merge($result, [
            'type' => $type,
            'customers' => $customers,
            'suppliers' => $suppliers,
            'customerId' => $customerId,
            'supplierId' => $supplierId,
            'start' => $start?->toDateString(),
            'end' => $end?->toDateString(),
        ]));
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
