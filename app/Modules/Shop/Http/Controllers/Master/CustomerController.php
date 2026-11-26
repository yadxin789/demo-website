<?php

namespace App\Modules\Shop\Http\Controllers\Master;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\Master\CustomerRequest;
use App\Modules\Shop\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(): View
    {
        $customers = Customer::orderBy('code')->get();

        return view('shop::master.customers.index', compact('customers'));
    }

    public function create(): View
    {
        return view('shop::master.customers.create');
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        Customer::create($request->validated());

        return redirect()->route('shop.customers.index')->with('status', '客户已创建');
    }

    public function edit(Customer $customer): View
    {
        return view('shop::master.customers.edit', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->validated());

        return redirect()->route('shop.customers.index')->with('status', '客户已更新');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('shop.customers.index')->with('status', '客户已删除');
    }
}
