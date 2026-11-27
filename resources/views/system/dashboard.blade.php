@extends('system.layout')

@section('title', '后台首页')
@section('sub', '账号与组织总览 · 不承载任何业务单据')

@section('content')
<div class="row g-3 mb-3">
    @foreach ([
        ['用户账号', $stats['users'], '个', 'fa-user-gear', $consoleBase.'/users'],
        ['部门', $stats['departments'], '个', 'fa-sitemap', $consoleBase.'/departments'],
        ['岗位', $stats['positions'], '个', 'fa-id-badge', $consoleBase.'/positions'],
        ['角色', $stats['roles'], '个', 'fa-user-shield', $consoleBase.'/roles'],
        ['权限点', $stats['permissions'], '条', 'fa-key', $consoleBase.'/roles'],
        ['数据表', $stats['tables'], '张', 'fa-database', $consoleBase.'/ops'],
    ] as [$k, $v, $u, $icon, $url])
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ $url }}" class="text-decoration-none text-reset">
                <div class="stat">
                    <div class="k"><i class="fa-solid {{ $icon }} me-1"></i>{{ $k }}</div>
                    <div class="v">{{ number_format($v) }}<span class="u ms-1">{{ $u }}</span></div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>最近账号</span>
                <a class="small text-decoration-none" href="{{ $consoleBase }}/users">全部用户</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>账号</th><th>姓名</th><th>部门 / 岗位</th><th>角色</th><th class="text-end">状态</th></tr></thead>
                    <tbody>
                        @forelse ($recentUsers as $u)
                            <tr>
                                <td class="font-monospace small">{{ $u['account'] ?: '—' }}</td>
                                <td>{{ $u['name'] ?: '—' }}</td>
                                <td class="small text-muted">{{ $u['department'] }} / {{ $u['position'] }}</td>
                                <td class="small">{{ $u['role'] }}</td>
                                <td class="text-end">
                                    <span class="badge {{ $u['active'] ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $u['active'] ? '启用' : '停用' }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">暂无账号</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">角色分布</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>角色</th><th>标识</th><th class="text-end">成员</th><th class="text-end">权限点</th></tr></thead>
                    <tbody>
                        @forelse ($roles as $r)
                            <tr>
                                <td>{{ $r['name'] }}</td>
                                <td class="font-monospace small text-muted">{{ $r['code'] }}</td>
                                <td class="text-end">{{ $r['users'] }}</td>
                                <td class="text-end">{{ $r['permissions'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-3">暂无角色</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">管理边界</div>
            <div class="card-body small text-muted">
                <p class="mb-2">本后台只管理系统本身：账号、组织、岗位、角色、数据导入引导、运行状态与基础配置。</p>
                <ul class="mb-3 ps-3">
                    <li>不创建、不审批、不过账任何业务单据；</li>
                    <li>不提供业务模块入口，业务操作请回各自工作台；</li>
                    <li>所有演示数据均为虚构名称，仅用于功能演示。</li>
                </ul>
                <div class="d-grid gap-2">
                    <a class="btn btn-sm btn-outline-secondary" href="{{ $consoleBase }}/ai-guide"><i class="fa-solid fa-wand-magic-sparkles me-1"></i>查看 AI 导入引导</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ $consoleBase }}/ops"><i class="fa-solid fa-heart-pulse me-1"></i>查看运维监控</a>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ $consoleBase }}/settings"><i class="fa-solid fa-sliders me-1"></i>查看系统设置</a>
                </div>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">数据库</div>
            <div class="card-body small">
                <div class="d-flex justify-content-between"><span class="text-muted">库文件</span><span class="font-monospace">{{ $stats['db_file'] }}</span></div>
                <div class="d-flex justify-content-between mt-2"><span class="text-muted">库大小</span><span>{{ number_format($stats['db_size'] / 1024, 1) }} KB</span></div>
                <div class="d-flex justify-content-between mt-2"><span class="text-muted">数据表</span><span>{{ $stats['tables'] }} 张</span></div>
            </div>
        </div>
    </div>
</div>
@endsection
