<?php

namespace App\Modules\Shop\Http\Controllers\Finance;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Services\Finance\FinancialReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class FinancialReportController extends Controller
{
    public function __construct(protected FinancialReportService $reports)
    {
    }

    public function index(Request $request): View
    {
        $tab = in_array($request->input('tab'), ['balance', 'income', 'cash'], true)
            ? $request->input('tab')
            : 'balance';

        $start = $this->parseDate($request->input('start')) ?? Carbon::today()->startOfMonth();
        $end = $this->parseDate($request->input('end')) ?? Carbon::today();

        $data = match ($tab) {
            'income' => ['income' => $this->reports->incomeStatement($start, $end)],
            'cash' => ['cash' => $this->reports->cashFlow($start, $end)],
            default => ['balance' => $this->reports->balanceSheet($end)],
        };

        return view('shop::finance.reports.index', array_merge($data, [
            'tab' => $tab,
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
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
