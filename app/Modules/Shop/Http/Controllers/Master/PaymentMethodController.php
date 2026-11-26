<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\PaymentMethodRequest;
use App\Modules\Shop\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function index(): View
    {
        $methods = PaymentMethod::orderBy('code')->get();

        return view('shop::master.payment-methods.index', compact('methods'));
    }

    public function create(): View
    {
        return view('shop::master.payment-methods.create');
    }

    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        PaymentMethod::create($request->validated());

        return redirect()->route('shop.payment-methods.index')->with('status', '支付方式已创建');
    }

    public function edit(PaymentMethod $payment_method): View
    {
        return view('shop::master.payment-methods.edit', compact('payment_method'));
    }

    public function update(PaymentMethodRequest $request, PaymentMethod $payment_method): RedirectResponse
    {
        $payment_method->update($request->validated());

        return redirect()->route('shop.payment-methods.index')->with('status', '支付方式已更新');
    }

    public function destroy(PaymentMethod $payment_method): RedirectResponse
    {
        $payment_method->delete();

        return redirect()->route('shop.payment-methods.index')->with('status', '支付方式已删除');
    }
}
