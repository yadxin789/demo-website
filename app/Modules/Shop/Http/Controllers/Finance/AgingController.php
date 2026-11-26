<?php

namespace App\Modules\Shop\Http\Controllers\Finance;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Customer;
use App\Modules\Shop\Models\Supplier;
use App\Modules\Shop\Services\Finance\AgingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class AgingController extends Controller
{
    public function __construct(protected AgingService $aging)
    {
    }

    public function index(Request $request): View
    {
        $type = $request->input('type') === 'payable' ? 'payable' : 'receivable';
        $partnerId = $request->filled('partner_id') ? (int) $request->input('partner_id') : null;
        $asOf = $this->parseDate($request->input('as_of')) ?? Carbon::today();

        $result = $type === 'payable'
            ? $this->aging->payables($partnerId, $asOf)
            : $this->aging->receivables($partnerId, $asOf);

        $partners = $type === 'payable'
            ? Supplier::orderBy('code')->get()
            : Customer::orderBy('code')->get();

        return view('shop::finance.aging.index', array_merge($result, [
            'type' => $type,
            'partners' => $partners,
            'partnerId' => $partnerId,
            'asOf' => $asOf->toDateString(),
            'labels' => AgingService::BUCKET_LABELS,
            'buckets' => AgingService::BUCKETS,
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
