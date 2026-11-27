@extends('hr::layouts.app')

@section('title', '角色权限')
@section('sub', '5 个角色 × 17 项权限，勾选即生效')

@section('content')
    <div class="hr-card">
        <h3>权限矩阵</h3>
        <div style="font-size:12px; color:#9a9aad; margin-bottom:10px">
            每个角色一列（可多选），保存后立即写入本产品的 spatie 表；guard 固定为 <b>hr</b>，与其它四个产品的权限完全隔离。
        </div>

        <div style="overflow-x:auto">
            <table class="hr-table">
                <thead>
                <tr>
                    <th style="min-width:140px">权限</th>
                    @foreach ($roles as $r)
                        <th style="text-align:center; min-width:110px">{{ $roleText[$r->name] ?? $r->name }}</th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach ($permissions as $p)
                    <tr>
                        <td><b>{{ $p->name }}</b></td>
                        @foreach ($roles as $r)
                            <td style="text-align:center">
                                @if (in_array($p->name, $matrix[$r->name] ?? [], true))
                                    <span style="color:#5f3dc4; font-size:15px">✓</span>
                                @else
                                    <span style="color:#d7d5e3">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                <tr>
                    <td><b>合计</b></td>
                    @foreach ($roles as $r)
                        <td style="text-align:center"><b>{{ count($matrix[$r->name] ?? []) }}</b> 项</td>
                    @endforeach
                </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-3 mt-1">
        @foreach ($roles as $r)
            <div class="col-lg-6">
                <div class="hr-card">
                    <h3>{{ $roleText[$r->name] ?? $r->name }} <span style="font-size:12px; color:#9a9aad">{{ $r->name }}</span></h3>
                    <form method="post" action="{{ route('hr.system.roles.permissions', $r->name) }}">
                        @csrf
                        <div style="display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:6px 12px; margin:10px 0">
                            @foreach ($permissions as $p)
                                <label style="display:flex; align-items:center; gap:6px; font-size:13px">
                                    <input name="permissions[]" type="checkbox" value="{{ $p->name }}"
                                           @checked(in_array($p->name, $matrix[$r->name] ?? [], true))>
                                    {{ $p->name }}
                                </label>
                            @endforeach
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-primary" type="submit">保存该角色</button>
                            <span style="font-size:12px; color:#9a9aad; align-self:center">当前 {{ count($matrix[$r->name] ?? []) }} 项</span>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endsection
