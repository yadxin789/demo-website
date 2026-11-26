<?php

namespace App\Modules\Shop\Http\Controllers\Finance;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Services\Finance\TaxReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class TaxReportController extends Controller
{
    public function __construct(protected TaxReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        $start = $this->parseDate($request->input('start'));
        $end = $this->parseDate($request->input('end'));

        $result = $this->reports->report($start, $end);

        return view('shop::finance.tax-reports.index', array_merge($result, [
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
