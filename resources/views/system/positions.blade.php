@extends('system.layout')

@section('title', '岗位管理')
@section('sub', '岗位序列与所属部门')

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">岗位列表（{{ count($positions) }}）</div>
            @if (! $hasTable)
                <div class="card-body text-muted small">该产品尚未建立岗位表，请先在数据库迁移中启用岗位序列。</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th style="width:60px;">ID</th><th>编码</th><th>岗位</th><th>所属部门</th><th class="text-end" style="width:100px;">状态</th></tr></thead>
                        <tbody>
                            @forelse ($positions as $p)
                                <tr>
                                    <td class="text-muted small">{{ $p['id'] }}</td>
                                    <td class="font-monospace small">{{ $p['code'] ?: '—' }}</td>
                                    <td>{{ $p['name'] }}</td>
                                    <td class="small text-muted">{{ $p['department'] }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ $consoleBase }}/positions/{{ $p['id'] }}">
                                            @csrf
                                            <input type="hidden" name="active" value="{{ ((int) $p['status'] === 1 || $p['status'] === 'active') ? '0' : '1' }}">
                                            <button class="btn btn-sm {{ ((int) $p['status'] === 1 || $p['status'] === 'active') ? 'btn-outline-secondary' : 'btn-outline-success' }}">
                                                {{ ((int) $p['status'] === 1 || $p['status'] === 'active') ? '停用' : '启用' }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">暂无岗位</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">新增岗位</div>
            <form class="card-body" method="POST" action="{{ $consoleBase }}/positions">
                @csrf
                <div class="mb-2">
                    <label class="form-label small mb-1">岗位名称</label>
                    <input type="text" name="name" class="form-control form-control-sm" required maxlength="64" placeholder="如：车间主任">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">岗位编码</label>
                    <input type="text" name="code" class="form-control form-control-sm" maxlength="32" placeholder="如：P-PROD-01">
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1">所属部门</label>
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">未指定</option>
                        @foreach ($departments as $d)
                            <option value="{{ $d['id'] }}">{{ $d['code'] ? $d['code'].' · ' : '' }}{{ $d['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="btn btn-sm btn-primary w-100" @disabled(! $hasTable)>创建岗位</button>
            </form>
        </div>
    </div>
</div>
@endsection
