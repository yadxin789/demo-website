@extends('shop::layouts.app')

@section('title', '费用报销')
@section('heading', '费用报销')

@section('content')
    @php
        $statusLabels = [
            'draft' => '草稿', 'confirmed' => '已入账', 'paid' => '已付款', 'void' => '已作废',
        ];
        $methodLabels = ['payable' => '挂账（其他应付款）', 'cash' => '现金支付', 'bank' => '银行转账'];
    @endphp

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><i class="fa-solid fa-plus me-1"></i>新建报销单</div>
        <div class="card-body">
            <form method="post" action="{{ route('shop.expenses.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label" for="expense_date">报销日期</label>
                        <input type="date" class="form-control" id="expense_date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="expense_account_id">费用科目</label>
                        <select class="form-select" id="expense_account_id" name="expense_account_id" required>
                            <option value="">请选择</option>
                            @foreach ($expenseAccounts as $account)
                                <option value="{{ $account->id }}" @selected(old('expense_account_id') == $account->id)>{{ $account->code }} · {{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="settlement_method">结算方式</label>
                        <select class="form-select" id="settlement_method" name="settlement_method" required>
                            @foreach ($methodLabels as $key => $label)
                                <option value="{{ $key }}" @selected(old('settlement_method', 'payable') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="payment_account_id">付款科目</label>
                        <select class="form-select" id="payment_account_id" name="payment_account_id">
                            <option value="">挂账时可不填</option>
                            @foreach ($paymentAccounts as $account)
                                <option value="{{ $account->id }}" @selected(old('payment_account_id') == $account->id)>{{ $account->code }} · {{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="payee">收款人/供应商</label>
                        <input type="text" class="form-control" id="payee" name="payee" value="{{ old('payee') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="department">部门</label>
                        <input type="text" class="form-control" id="department" name="department" value="{{ old('department') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="description">事由</label>
                        <input type="text" class="form-control" id="description" name="description" value="{{ old('description') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="reference">单据参考号</label>
                        <input type="text" class="form-control" id="reference" name="reference" value="{{ old('reference') }}">
                    </div>
                </div>

                <hr>
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label" for="line_description">费用明细</label>
                        <input type="text" class="form-control" id="line_description" name="lines[0][description]" value="{{ old('lines.0.description', '费用报销') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="line_net">不含税金额</label>
                        <input type="number" step="0.0001" min="0" class="form-control" id="line_net" name="lines[0][amount_net]" value="{{ old('lines.0.amount_net', '0') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" for="line_tax">税额</label>
                        <input type="number" step="0.0001" min="0" class="form-control" id="line_tax" name="lines[0][tax_amount]" value="{{ old('lines.0.tax_amount', '0') }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">保存草稿</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <form method="get" action="{{ route('shop.expenses.index') }}" class="row g-2 mb-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="status">状态</label>
            <select class="form-select" id="status" name="status">
                <option value="">全部</option>
                @foreach ($statusLabels as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2"><button class="btn btn-outline-secondary" type="submit">筛选</button></div>
    </form>

    <div class="card p-0">
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>单据号</th><th>日期</th><th>费用科目</th><th>摘要</th><th>结算</th>
                        <th class="text-end">不含税</th><th class="text-end">税额</th><th class="text-end">合计</th>
                        <th>凭证</th><th>状态</th><th class="text-end">操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->code }}</td>
                            <td>{{ optional($expense->expense_date)->toDateString() }}</td>
                            <td>{{ $expense->expenseAccount?->name }}</td>
                            <td>{{ $expense->description ?: $expense->payee }}</td>
                            <td>{{ $methodLabels[$expense->settlement_method] ?? $expense->settlement_method }}</td>
                            <td class="text-end">{{ number_format((float) $expense->amount_net, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $expense->tax_amount, 4) }}</td>
                            <td class="text-end">{{ number_format((float) $expense->total_amount, 4) }}</td>
                            <td>{{ $expense->entry?->code ?: '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $expense->status === 'paid' ? 'success' : ($expense->status === 'void' ? 'secondary' : 'info') }}">
                                    {{ $statusLabels[$expense->status] ?? $expense->status }}
                                </span>
                            </td>
                            <td class="text-end">
                                @if ($expense->status === 'draft')
                                    <form method="post" action="{{ route('shop.expenses.confirm', $expense->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-primary" type="submit">确认入账</button>
                                    </form>
                                @endif
                                @if ($expense->status === 'confirmed')
                                    <form method="post" action="{{ route('shop.expenses.pay', $expense->id) }}" class="d-inline-flex gap-1 align-items-center">
                                        @csrf
                                        <select name="payment_account_id" class="form-select form-select-sm" required>
                                            <option value="">付款科目</option>
                                            @foreach ($paymentAccounts as $account)
                                                <option value="{{ $account->id }}">{{ $account->code }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-sm btn-success" type="submit">付款</button>
                                    </form>
                                @endif
                                @if (in_array($expense->status, ['draft', 'confirmed'], true))
                                    <form method="post" action="{{ route('shop.expenses.void', $expense->id) }}" class="d-inline" onsubmit="return confirm('确认作废该报销单？已入账凭证将自动冲销。')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">作废</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">暂无报销单</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-3">{{ $expenses->links() }}</div>
@endsection
