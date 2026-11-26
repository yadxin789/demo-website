<?php

namespace App\Modules\Shop\Http\Controllers\Finance;

use App\Modules\Shop\Exceptions\AccountingException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Services\Finance\ClosingService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClosingController extends Controller
{
    public function __construct(protected ClosingService $closing)
    {
    }

    public function index(): View
    {
        return view('shop::finance.closing.index', [
            'periods' => $this->closing->periods(),
        ]);
    }

    public function close(Request $request, int $id)
    {
        try {
            $this->closing->closePeriod($id, auth()->id());
        } catch (AccountingException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('shop.closing.index')->with('status', '期间已结账关闭');
    }
}
