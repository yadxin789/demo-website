<?php

namespace App\Modules\Erp\Http\Controllers;

use App\Modules\Erp\Http\Requests\Customer\CustomerStoreRequest;
use App\Modules\Erp\Http\Requests\Customer\CustomerUpdateRequest;
use App\Modules\Erp\Models\Finance\PaymentTerm;
use App\Modules\Erp\Models\MasterData\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($request->filled('keyword')) {
            $kw = $request->input('keyword');
            $query->where(function ($q) use ($kw) {
                $q->where('customer_code', 'like', "%{$kw}%")
                    ->orWhere('customer_name', 'like', "%{$kw}%")
                    ->orWhere('contact_person', 'like', "%{$kw}%");
            });
        }

        $customers = $query->orderBy('customer_code')->paginate(20)->withQueryString();

        return view('erp::customers.index', compact('customers'));
    }

    public function create()
    {
        return view('erp::customers.create', ['paymentTerms' => $this->paymentTerms()]);
    }

    public function store(CustomerStoreRequest $request)
    {
        $validated = $request->validated();
        $validated['status'] = 1;

        Customer::create($validated);

        return redirect()->route('erp.customers.index')->with('success', '客户创建成功。');
    }

    public function edit(Customer $customer)
    {
        return view('erp::customers.edit', [
            'customer' => $customer,
            'paymentTerms' => $this->paymentTerms(),
        ]);
    }

    public function update(CustomerUpdateRequest $request, Customer $customer)
    {
        $validated = $request->validated();

        $customer->update($validated);

        return redirect()->route('erp.customers.index')->with('success', '客户更新成功。');
    }

    private function paymentTerms()
    {
        return PaymentTerm::query()
            ->where('status', 1)
            ->orderBy('days')
            ->orderBy('name')
            ->get();
    }
}
