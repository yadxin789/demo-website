@extends('mes::layouts.app')

@section('title', '系统 · 部门管理')
@section('sub', '车间 / 班组的组织结构，账号与岗位都挂在这里')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">部门数</div>
            <div class="num">{{ $departments->count() }}</div>
            <div class="hint">个</div>
        </div>
        <div class="mes-kpi ok">
            <div class="lbl">启用中</div>
            <div class="num">{{ $departments->where('status', 'active')->count() }}</div>
            <div class="hint">可挂账号</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">挂靠账号</div>
            <div class="num">{{ $departments->sum('users_count') }}</div>
            <div class="hint">人</div>
        </div>
        <div class="mes-kpi">
            <div class="lbl">岗位数</div>
            <div class="num">{{ $departments->sum('positions_count') }}</div>
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
        <div style="font-weight:600; margin-bottom:10px">新增部门</div>
        <form method="post" action="{{ route('mes.system.departments.store') }}" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center">
            @csrf
            <input name="name" class="form-control form-control-sm" style="width:200px" placeholder="部门名称，如 SMT 车间" required>
            <select name="parent_id" class="form-select form-select-sm" style="width:180px">
                <option value="">无上级（厂级）</option>
                @foreach ($parents as $p)
                    <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
            </select>
            <input name="sort" type="number" class="form-control form-control-sm" style="width:90px" placeholder="排序" value="0">
            <select name="status" class="form-select form-select-sm" style="width:100px">
                <option value="active">启用</option>
                <option value="disabled">停用</option>
            </select>
            <button class="btn btn-sm btn-primary">新增</button>
            <span style="font-size:12px; color:#8a97a8">排序数字越小越靠前</span>
        </form>
    </div>

    <div class="mes-card">
        <table class="mes-table" style="font-size:13px">
            <thead>
            <tr><th style="width:70px">排序</th><th>部门</th><th>上级</th><th>账号</th><th>岗位</th><th>状态</th><th style="width:520px">编辑</th></tr>
            </thead>
            <tbody>
            @forelse ($departments as $d)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $d->sort }}</td>
                    <td>
                        @if ($d->parent_id)<span style="color:#8a97a8; margin-right:4px">└</span>@endif
                        <b>{{ $d->name }}</b>
                    </td>
                    <td style="color:#6b7a90">{{ $d->parent?->name ?? '—' }}</td>
                    <td>{{ $d->users_count }}</td>
                    <td>{{ $d->positions_count }}</td>
                    <td><span class="mes-badge {{ $d->status === 'active' ? 'b-done' : 'b-bad' }}">{{ $d->status === 'active' ? '启用' : '停用' }}</span></td>
                    <td>
                        <div style="display:flex; gap:6px; align-items:center">
                            <form method="post" action="{{ route('mes.system.departments.update', $d) }}" style="display:flex; gap:6px; align-items:center">
                                @csrf
                                @method('PUT')
                                <input name="name" class="form-control form-control-sm" style="width:140px" value="{{ $d->name }}" required>
                                <select name="parent_id" class="form-select form-select-sm" style="width:130px">
                                    <option value="">无上级</option>
                                    @foreach ($parents as $p)
                                        @continue($p->id === $d->id)
                                        <option value="{{ $p->id }}" @selected($d->parent_id === $p->id)>{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                <input name="sort" type="number" class="form-control form-control-sm" style="width:70px" value="{{ $d->sort }}">
                                <select name="status" class="form-select form-select-sm" style="width:86px">
                                    <option value="active" @selected($d->status === 'active')>启用</option>
                                    <option value="disabled" @selected($d->status === 'disabled')>停用</option>
                                </select>
                                <button class="btn btn-sm btn-outline-primary">保存</button>
                            </form>
                            <form method="post" action="{{ route('mes.system.departments.destroy', $d) }}" onsubmit="return confirm('删除部门 {{ $d->name }}？')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="mes-empty">还没有部门，先在上面新增一个。</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
