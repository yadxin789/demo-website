<?php

namespace App\Modules\Shop\Http\Controllers\Finance;

use App\Modules\Shop\Exceptions\AccountingException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Account;
use App\Modules\Shop\Models\Expense;
use App\Modules\Shop\Services\Finance\ExpenseService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $expenses)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->input('status');
        $list = Expense::query()
            ->with(['expenseAccount', 'partner'])
            ->when($status && in_array($status, [
                Expense::STATUS_DRAFT, Expense::STATUS_CONFIRMED, Expense::STATUS_PAID, Expense::STATUS_VOID,
            ], true), fn ($q) => $q->where('status', $status))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('shop::finance.expenses.index', [
            'expenses' => $list,
            'status' => $status,
            'expenseAccounts' => Account::where('type', Account::TYPE_EXPENSE)->where('is_active', true)->orderBy('code')->get(),
            'paymentAccounts' => Account::whereIn('code', ['1004', '1005'])->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_date' => ['required', 'date'],
            'expense_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'settlement_method' => ['required', 'in:payable,cash,bank'],
            'payment_account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'payee' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.amount_net' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $expense = $this->expenses->createExpense($data + ['creator_id' => auth()->id()], $data['lines']);
        } catch (AccountingException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('shop.expenses.index')->with('status', "报销单 {$expense->code} 已创建");
    }

    public function confirm(int $id)
    {
        try {
            $this->expenses->confirm(Expense::findOrFail($id));
        } catch (AccountingException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('shop.expenses.index')->with('status', '报销单已确认入账');
    }

    public function pay(Request $request, int $id)
    {
        $data = $request->validate([
            'payment_account_id' => ['required', 'integer', 'exists:accounts,id'],
        ]);

        try {
            $this->expenses->pay(Expense::findOrFail($id), (int) $data['payment_account_id']);
        } catch (AccountingException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('shop.expenses.index')->with('status', '报销单已付款');
    }

    public function void(int $id)
    {
        try {
            $this->expenses->void(Expense::findOrFail($id));
        } catch (AccountingException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('shop.expenses.index')->with('status', '报销单已作废');
    }
}
