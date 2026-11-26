<?php

namespace App\Modules\Shop\Http\Controllers\Finance;

use App\Modules\Shop\Exceptions\AccountingException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Account;
use App\Modules\Shop\Models\BankStatement;
use App\Modules\Shop\Services\Finance\ReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReconciliationController extends Controller
{
    public function __construct(protected ReconciliationService $reconciliation)
    {
    }

    public function index(Request $request): View
    {
        $accountId = $request->filled('account_id') ? (int) $request->input('account_id') : null;
        $start = $this->parseDate($request->input('start')) ?? Carbon::today()->subDays(29);
        $end = $this->parseDate($request->input('end')) ?? Carbon::today();
        $status = $request->filled('status') ? (string) $request->input('status') : null;

        $systemLines = $this->reconciliation->systemLines($accountId, $start, $end, $status);
        $statements = $this->reconciliation->statementLines($accountId, $start, $end, $status);
        $suggestions = collect($this->reconciliation->suggestMatches($accountId, $start, $end)['statements'])
            ->keyBy('statement_id');

        $accounts = Account::query()->where('type', Account::TYPE_ASSET)->orderBy('code')->get();

        $systemOpen = $systemLines->where('matched', false)->count();
        $bankOpen = $statements->where('matched', false)->count();

        return view('shop::finance.reconciliations.index', [
            'accounts' => $accounts,
            'accountId' => $accountId,
            'start' => $start?->toDateString(),
            'end' => $end?->toDateString(),
            'status' => $status,
            'systemLines' => $systemLines,
            'statements' => $statements,
            'suggestions' => $suggestions,
            'systemOpen' => $systemOpen,
            'bankOpen' => $bankOpen,
        ]);
    }

    public function import(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        try {
            $result = $this->reconciliation->importCsv(
                $request->file('file')->getRealPath(),
                (int) $validated['account_id'],
                auth()->id(),
            );
        } catch (AccountingException $e) {
            return redirect()->route('shop.reconciliations.index', ['account_id' => $validated['account_id']])
                ->with('error', $e->getMessage());
        }

        return redirect()->route('shop.reconciliations.index', ['account_id' => $validated['account_id']])
            ->with('status', "银行流水导入完成：成功 {$result['imported']} 条，重复 {$result['duplicated']} 条，无效 ".count($result['invalid']).' 条');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'statement_date' => 'required|date',
            'direction' => 'required|in:receive,pay',
            'amount' => 'required|numeric|min:0.0001',
            'counterparty' => 'nullable|string|max:255',
            'reference' => 'nullable|string|max:64',
            'memo' => 'nullable|string|max:255',
        ]);

        $this->reconciliation->addStatement($validated, auth()->id());

        return redirect()->route('shop.reconciliations.index', $request->only(['account_id', 'start', 'end', 'status']))
            ->with('status', '银行流水已登记');
    }

    public function match(Request $request)
    {
        $validated = $request->validate([
            'statement_id' => 'required|exists:bank_statements,id',
            'entry_line_id' => 'required|exists:account_entry_lines,id',
        ]);

        try {
            $this->reconciliation->match(
                (int) $validated['statement_id'],
                (int) $validated['entry_line_id'],
                auth()->id()
            );
        } catch (AccountingException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('shop.reconciliations.index')
            ->with('status', '已勾对');
    }

    public function unmatch(int $id)
    {
        try {
            $this->reconciliation->unmatch($id, auth()->id());
        } catch (AccountingException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('shop.reconciliations.index')
            ->with('status', '已解除勾对');
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
