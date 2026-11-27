@extends('mes::layouts.app')

@section('title', '系统 · 岗位管理')
@section('sub', '岗位挂在部门下，账号创建时从这里选岗')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">岗位数</div>
            <div class="num">{{ $positions->count() }}</div>
            <div class="hint">个</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">启用中</div>
            <div class="num">{{ $positions->where('status', 'active')->count() }}</div>
            <div class="hint">可用</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">已占用</div>
            <div class="num">{{ $positions->sum('users_count') }}</div>
            <div class="hint">人</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">覆盖部门</div>
            <div class="num">{{ $positions->pluck('department_id')->filter()->unique()->count() }}</div>
            <div class="hint">个</div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success py-2" style="font-size:13px">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger py-2" style="font-size:13px">{{ $errors->first() }}</div>
    @endif

    <div class="mes-card" style="margin-bottom:14px">
        <div style="font-weight:600; margin-bottom:10px">新增岗位</div>
        <form method="post" action="{{ route('mes.system.positions.store') }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
            @csrf
            <input name="name" class="form-control form-control-sm" style="width:220px" placeholder="岗位名称，如 设备工程师" required>
            <select name="department_id" class="form-select form-select-sm" style="width:200px">
                <option value="">不指定部门</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}">{{ $d->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select form-select-sm" style="width:100px">
                <option value="active">启用</option>
                <option value="disabled">停用</option>
            </select>
            <button class="btn btn-sm btn-primary">新增</button>
        </form>
    </div>

    <div class="mes-card">
        <table class="mes-table" style="font-size:13px">
            <thead>
            <tr><th>岗位</th><th>所属部门</th><th>在岗人数</th><th>状态</th><th style="width:400px">编辑</th></tr>
            </thead>
            <tbody>
            @forelse ($positions as $p)
                <tr>
                    <td><b>{{ $p->name }}</b></td>
                    <td style="color:#6b7a90">{{ $p->department?->name ?? '—' }}</td>
                    <td>{{ $p->users_count }}</td>
                    <td><span class="mes-badge {{ $p->status === 'active' ? 'b-done' : 'b-bad' }}">{{ $p->status === 'active' ? '启用' : '停用' }}</span></td>
                    <td>
                        <div style="display:flex; gap:6px; align-items:center">
                            <form method="post" action="{{ route('mes.system.positions.update', $p) }}" style="display:flex; gap:6px; align-items:center">
                                @csrf
                                @method('PUT')
                                <input name="name" class="form-control form-control-sm" style="width:170px" value="{{ $p->name }}" required>
                                <select name="department_id" class="form-select form-select-sm" style="width:170px">
                                    <option value="">不指定</option>
                                    @foreach ($departments as $d)
                                        <option value="{{ $d->id }}" @selected($p->department_id === $d->id)>{{ $d->name }}</option>
                                    @endforeach
                                </select>
                                <select name="status" class="form-select form-select-sm" style="width:86px">
                                    <option value="active" @selected($p->status === 'active')>启用</option>
                                    <option value="disabled" @selected($p->status === 'disabled')>停用</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary">保存</button>
                            </form>
                            <form method="post" action="{{ route('mes.system.positions.destroy', $p) }}" onsubmit="return confirm('删除岗位 {{ $p->name }}？')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="mes-empty">还没有岗位，先在上面新增一个。</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
