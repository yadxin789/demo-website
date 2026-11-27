@extends('system.layout')

@section('title', '部门管理')
@section('sub', '组织层级与排序，不涉及业务权限')

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">部门列表（{{ count($departments) }}）</div>
            @if (! $hasTable)
                <div class="card-body text-muted small">该产品尚未建立部门表，请先在数据库迁移中启用组织架构。</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th style="width:60px;">ID</th><th>编码</th><th>名称</th><th style="width:190px;">排序 / 状态</th></tr></thead>
                        <tbody>
                            @forelse ($departments as $d)
                                <tr>
                                    <td class="text-muted small">{{ $d['id'] }}</td>
                                    <td class="font-monospace small">{{ $d['code'] ?: '—' }}</td>
                                    <td>{{ $d['name'] }}</td>
                                    <td>
                                        <form method="POST" action="{{ $consoleBase }}/departments/{{ $d['id'] }}" class="d-flex gap-1">
                                            @csrf
                                            <input type="number" name="sort" value="{{ $d['sort'] }}" min="0" class="form-control form-control-sm" style="width:70px;">
                                            <select name="active" class="form-select form-select-sm" style="width:82px;">
                                                <option value="1" @selected((int) $d['status'] === 1 || $d['status'] === 'active')>启用</option>
                                                <option value="0" @selected((int) $d['status'] === 0 || $d['status'] === 'disabled')>停用</option>
                                            </select>
                                            <button class="btn btn-sm btn-outline-secondary">保存</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">暂无部门</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">新增部门</div>
            <form class="card-body" method="POST" action="{{ $consoleBase }}/departments">
                @csrf
                <div class="mb-2">
                    <label class="form-label small mb-1">部门名称</label>
                    <input type="text" name="name" class="form-control form-control-sm" required maxlength="64" placeholder="如：生产部">
                </div>
                <div class="mb-2">
                    <label class="form-label small mb-1">部门编码</label>
                    <input type="text" name="code" class="form-control form-control-sm" maxlength="32" placeholder="如：D-PROD">
                </div>
                <div class="mb-3">
                    <label class="form-label small mb-1">排序号</label>
                    <input type="number" name="sort" class="form-control form-control-sm" min="0" value="0">
                </div>
                <button class="btn btn-sm btn-primary w-100" @disabled(! $hasTable)>创建部门</button>
            </form>
        </div>
        <div class="card mt-3">
            <div class="card-body small text-muted">
                部门只用于组织归属展示与报表分组；本系统不按部门分配业务权限，避免后台影响前台流程。
            </div>
        </div>
    </div>
</div>
@endsection
