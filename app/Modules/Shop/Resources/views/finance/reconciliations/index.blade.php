@extends('shop::layouts.app')

@section('title', '银行对账')
@section('heading', '银行对账')

@section('content')
    @php
        $dirLabel = ['receive' => '入账', 'pay' => '出账'];
        $openSystem = $systemLines->where('matched', false)->values();
        // 同科目同方向的未配对系统分录行，供勾对下拉选择
        $candidatesByDirection = $openSystem->groupBy(fn ($line) => $line['account_id'].'-'.$line['direction']);
    @endphp

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
    @endif

    <form method="get" action="{{ route('shop.reconciliations.index') }}" class="row g-2 mb-3 align-items-end" aria-label="对账筛选">
        <div class="col-12 col-md-3">
            <label for="account_id" class="form-label">科目</label>
            <select id="account_id" name="account_id" class="form-select">
                <option value="">全部资产科目</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected($accountId === $account->id)>
                        {{ $account->code }} · {{ $account->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-12 col-md-2">
            <label for="start" class="form-label">起始日期</label>
            <input type="date" id="start" name="start" value="{{ $start }}" class="form-control">
        </div>
        <div class="col-12 col-md-2">
            <label for="end" class="form-label">截止日期</label>
            <input type="date" id="end" name="end" value="{{ $end }}" class="form-control">
        </div>
        <div class="col-12 col-md-2">
            <label for="status" class="form-label">勾对状态</label>
            <select id="status" name="status" class="form-select">
                <option value="">全部</option>
                <option value="unmatched" @selected($status === 'unmatched')>未勾对</option>
                <option value="matched" @selected($status === 'matched')>已勾对</option>
            </select>
        </div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i>查询</button>
            <a href="{{ route('shop.reconciliations.index') }}" class="btn btn-outline-secondary">重置</a>
        </div>
    </form>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">系统账未达账项</div>
                <div class="fs-5 fw-bold">{{ $systemOpen }} 笔</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">银行流水未达账项</div>
                <div class="fs-5 fw-bold">{{ $bankOpen }} 笔</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small">银行流水合计</div>
                <div class="fs-5 fw-bold">{{ $statements->count() }} 笔</div>
            </div>
        </div>
    </div>

    <details class="card mb-3">
        <summary class="card-header">登记银行流水</summary>
        <div class="card-body">
            <form method="post" action="{{ route('shop.reconciliations.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-12 col-md-3">
                    <label for="account_id" class="form-label">科目</label>
                    <select name="account_id" class="form-select" required>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="statement_date" class="form-label">日期</label>
                    <input type="date" name="statement_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-12 col-md-2">
                    <label for="direction" class="form-label">方向</label>
                    <select name="direction" class="form-select" required>
                        <option value="receive">入账</option>
                        <option value="pay">出账</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label for="amount" class="form-label">金额</label>
                    <input type="number" step="0.0001" min="0.0001" name="amount" class="form-control" required>
                </div>
                <div class="col-12 col-md-3">
                    <label for="reference" class="form-label">对方 / 回单号</label>
                    <input type="text" name="reference" class="form-control" placeholder="对方户名或回单号">
                </div>
                <div class="col-12">
                    <label for="memo" class="form-label">摘要</label>
                    <input type="text" name="memo" class="form-control" maxlength="255">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>登记流水</button>
                </div>
            </form>
        </div>
    </details>

    <details class="card mb-3">
        <summary class="card-header">批量导入银行流水 CSV</summary>
        <div class="card-body">
            <form method="post" action="{{ route('shop.reconciliations.import') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-12 col-md-4">
                    <label for="import_account_id" class="form-label">银行科目</label>
                    <select id="import_account_id" name="account_id" class="form-select" required>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}">{{ $account->code }} · {{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label for="bank_file" class="form-label">CSV 文件</label>
                    <input id="bank_file" type="file" name="file" class="form-control" accept=".csv,.txt" required>
                    <div class="form-text">表头至少包含「日期」「金额」，可选「方向」「对方」「回单号」「摘要」；支持 UTF-8/GBK。</div>
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-import me-1"></i>导入并去重</button>
                </div>
            </form>
        </div>
    </details>

    <div class="card p-0 mb-3">
        <div class="card-header">银行流水（对账单）</div>
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">银行流水勾对清单</caption>
                <thead>
                    <tr>
                        <th scope="col">日期</th>
                        <th scope="col">方向</th>
                        <th scope="col" class="text-end">金额</th>
                        <th scope="col">对方 / 回单号</th>
                        <th scope="col">摘要</th>
                        <th scope="col">状态</th>
                        <th scope="col">勾对操作</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($statements as $row)
                        <tr>
                            <td>{{ $row['date'] ?: '—' }}</td>
                            <td>{{ $dirLabel[$row['direction']] ?? $row['direction'] }}</td>
                            <td class="text-end">{{ number_format($row['amount'], 4) }}</td>
                            <td>{{ $row['counterparty'] ?: $row['reference'] ?: '—' }}</td>
                            <td>{{ $row['memo'] ?: '—' }}</td>
                            <td>
                                @if ($row['matched'])
                                    <span class="badge text-bg-success">已勾对</span>
                                @else
                                    <span class="badge text-bg-warning">未达</span>
                                @endif
                            </td>
                            <td>
                                @if ($row['matched'])
                                    <span class="text-muted small">{{ $row['entry_code'] }}</span>
                                    <form method="post" action="{{ route('shop.reconciliations.unmatch', $row['reconciliation_id']) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">解除</button>
                                    </form>
                                @else
                                    @php
                                        $suggestion = $suggestions->get($row['id']);
                                        $best = $suggestion['best'] ?? null;
                                    @endphp
                                    @if ($best)
                                        <div class="small text-muted mb-1">
                                            AI 建议：{{ $best['entry_code'] }}（{{ $best['date'] }}，日期差 {{ $best['day_diff'] }} 天，匹配分 {{ $best['score'] }}）
                                            @if ($suggestion['ambiguous'] ?? false)
                                                <span class="text-warning">存在同分候选</span>
                                            @endif
                                        </div>
                                    @endif
                                    <form method="post" action="{{ route('shop.reconciliations.match') }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="hidden" name="statement_id" value="{{ $row['id'] }}">
                                        <select name="entry_line_id" class="form-select form-select-sm" required>
                                            <option value="">选择系统分录</option>
                                            @foreach ($candidatesByDirection->get($row['account_id'].'-'.$row['direction'], []) as $candidate)
                                                <option value="{{ $candidate['entry_line_id'] }}" @selected($best && $best['entry_line_id'] === $candidate['entry_line_id'])>
                                                    {{ $candidate['date'] }} · {{ $candidate['entry_code'] }} · {{ number_format($candidate['amount'], 4) }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary">勾对</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">所选期间暂无银行流水</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-0">
        <div class="card-header">系统账分录（未达账项）</div>
        <div class="table-wrapper">
            <table class="table table-hover mb-0">
                <caption class="visually-hidden">系统账未达分录清单</caption>
                <thead>
                    <tr>
                        <th scope="col">日期</th>
                        <th scope="col">凭证号</th>
                        <th scope="col">方向</th>
                        <th scope="col" class="text-end">金额</th>
                        <th scope="col">摘要</th>
                        <th scope="col">状态</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($openSystem as $row)
                        <tr>
                            <td>{{ $row['date'] ?: '—' }}</td>
                            <td>{{ $row['entry_code'] ?: '—' }}</td>
                            <td>{{ $dirLabel[$row['direction']] ?? $row['direction'] }}</td>
                            <td class="text-end">{{ number_format($row['amount'], 4) }}</td>
                            <td>{{ $row['memo'] ?: '—' }}</td>
                            <td><span class="badge text-bg-warning">未达</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">系统账暂无未达分录</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
