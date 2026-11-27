@extends('mes::layouts.app')

@section('title', $title)
@section('sub', '该模块正在建设')

@section('content')
    <div class="mes-card" style="max-width:720px">
        <h3>{{ $title }} · 建设中</h3>
        <p style="color:#42526b; line-height:1.9; margin:0 0 14px">
            这个页面属于 <b>{{ $phase }}</b> 阶段，当前是占位状态，功能随后补齐。
        </p>
        <p style="color:#6b7a90; line-height:1.9; margin:0 0 18px">{{ $desc }}</p>
        <div style="background:#f8f9fb; border:1px dashed #dfe4ee; border-radius:8px; padding:12px 14px; font-size:13px; color:#6b7a90">
            同站已在运行的模块：工作台、登录、账户体系、数据库结构（33 张表）。<br>
            想看完整已交付的产品，可以去站点上的 ERP、项目管理、零售系统。
        </div>
        <div style="margin-top:18px; display:flex; gap:10px">
            <a class="btn btn-sm btn-dark" href="{{ route('mes.dashboard') }}">返回工作台</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('portal.home') }}">回产品列表</a>
        </div>
    </div>
@endsection
