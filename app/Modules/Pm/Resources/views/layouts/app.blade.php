<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <link rel="icon" type="image/svg+xml" href="{{ asset('assets/pm/favicon.svg') }}">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', '产品研发管理系统')</title>
    <link rel="stylesheet" href="{{ asset('assets/pm/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/pm/css/font-awesome.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/pm/css/v4-shims.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/pm/css/style.css') }}">
    <style>
        /* 移动端抽屉：复用桌面同一份菜单，深底白字 */
        #mobileSidebar .nav-link { color: #fff; padding: 10px 16px; }
        #mobileSidebar .nav-link.active { background: rgba(255,255,255,.14); color: #fff; }
        #mobileSidebar .badge { font-size: 11px; }
        /* 窄屏顶栏：保留铃铛与头像下拉，隐藏冗长文字 */
        @media (max-width: 991.98px) { .navbar .navbar-nav { flex-direction: row; flex-wrap: nowrap; } }
        @media (max-width: 575.98px) { .user-info .user-name, .user-info .user-role { display: none; } }
        body {
            background-color: #f5f7fa;
        }
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
        }
        .sidebar a {
            color: #fff;
            text-decoration: none;
        }
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .active {
            background-color: rgba(102, 126, 234, 0.3);
            border-left: 3px solid #667eea;
        }
        .navbar {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .user-info {
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 0.75rem;
        }
        .user-name {
            color: #333;
            font-size: 0.9rem;
        }
        .user-role {
            color: #666;
            font-size: 0.75rem;
        }
        .stat-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    </style>
    @yield('head')
    {{-- 五产品统一外框皮肤（S10）：放在最后加载，覆盖本产品自带样式 --}}
    <style>:root { --brand: {{ config('products.products.pm.theme.primary') }}; --brand-dark: {{ config('products.products.pm.theme.accent') }}; }</style>
    <link rel="stylesheet" href="{{ asset('assets/shared/css/shell.css') }}">
    {{-- 移动端适配（S19）：抽屉式侧栏 + 表格横向滚动 --}}
    <link rel="stylesheet" href="{{ asset('assets/shared/css/responsive.css') }}">
    <script defer src="{{ asset('assets/shared/js/responsive.js') }}"></script>
</head>
<body>
    @php
        $currentUser = auth()->user();
        $isAdmin = $currentUser ? $currentUser->isAdmin() : false;
        $isManager = $currentUser ? $currentUser->isProjectCreator() : false;
        $isProjectManager = $currentUser ? $currentUser->isProjectManager() : false;
        $isDeptManager = $currentUser ? $currentUser->isTaskAuditor() : false;
        $isExecutor = $currentUser ? $currentUser->isTaskExecutor() : false;
        $isSupport = $currentUser ? $currentUser->isSupport() : false;
        $userRole = $currentUser && $currentUser->role ? $currentUser->role->name : '未知';
        $userName = $currentUser ? $currentUser->name : '未知';
        
        // 计算侧边栏菜单中的任务计数
        $pendingExtensionCount = 0;
        // 项目状态徽章计数（用户参与的项目）
        $myOverdueProjectCount = 0;
        $myInProgressProjectCount = 0;
        $myCompletedProjectCount = 0;
        $mySuspendedProjectCount = 0;
        $myProjectCount = 0;
        // 执行人任务状态徽章计数
        $myOverdueTaskCount = 0;
        $myInProgressTaskCount = 0;
        $myCompletedTaskCount = 0;
        $mySuspendedTaskCount = 0;
        $myPendingExecutionTaskCount = 0;
        // 审核人任务状态徽章计数
        $myOverdueAuditCount = 0;
        $myInProgressAuditCount = 0;
        $myCompletedAuditCount = 0;
        $mySuspendedAuditCount = 0;
        $myPendingAuditTaskCount = 0;
        
        if ($currentUser) {
            // 用户参与的项目ID
            $executorProjectIds = \App\Modules\Pm\Models\Task::where('executor_id', $currentUser->id)->pluck('project_id');
            $auditorProjectIds = \App\Modules\Pm\Models\Task::where('auditor_id', $currentUser->id)->pluck('project_id');
            $creatorProjectIds = \App\Modules\Pm\Models\Project::where('creator_id', $currentUser->id)->pluck('id');
            $myProjectIds = $executorProjectIds->merge($auditorProjectIds)->merge($creatorProjectIds)->unique();
            
            // 徽章显示所有状态的项目总数（含未开始、逾期完成），明细在“我的项目列表”属性页中查看。
            $myProjectCount = \App\Modules\Pm\Models\Project::whereIn('id', $myProjectIds)->count();
            
            // 执行人任务计数（排除暂停项目中的任务）
            $myOverdueTaskCount = \App\Modules\Pm\Models\Task::where('executor_id', $currentUser->id)
                ->where('status', \App\Modules\Pm\States\Task\Overdue::class)
                ->whereHas('project', fn($q) => $q->where('status', '!=', \App\Modules\Pm\States\Project\Suspended::class))
                ->count();
            $myInProgressTaskCount = \App\Modules\Pm\Models\Task::where('executor_id', $currentUser->id)
                ->whereIn('status', [
                    \App\Modules\Pm\States\Task\Pending::class,
                    \App\Modules\Pm\States\Task\Rejected::class,
                    \App\Modules\Pm\States\Task\InProgress::class,
                    \App\Modules\Pm\States\Task\ForAudit::class,
                ])
                ->whereHas('project', fn($q) => $q->where('status', '!=', \App\Modules\Pm\States\Project\Suspended::class))
                ->count();
            $myCompletedTaskCount = \App\Modules\Pm\Models\Task::where('executor_id', $currentUser->id)
                ->whereIn('status', [
                    \App\Modules\Pm\States\Task\Completed::class,
                    \App\Modules\Pm\States\Task\OverdueCompleted::class,
                ])
                ->whereHas('project', fn($q) => $q->where('status', '!=', \App\Modules\Pm\States\Project\Suspended::class))
                ->count();
            $mySuspendedTaskCount = \App\Modules\Pm\Models\Task::where('executor_id', $currentUser->id)
                ->whereHas('project', fn($q) => $q->where('status', \App\Modules\Pm\States\Project\Suspended::class))->count();
            // 待执行任务入口统计需要执行人上传或重新上传文件的任务。
            $myPendingExecutionTaskCount = \App\Modules\Pm\Models\Task::where('executor_id', $currentUser->id)
                ->whereIn('status', [
                    \App\Modules\Pm\States\Task\Pending::class,
                    \App\Modules\Pm\States\Task\InProgress::class,
                    \App\Modules\Pm\States\Task\Overdue::class,
                    \App\Modules\Pm\States\Task\Rejected::class,
                ])
                ->whereHas('project', fn($q) => $q->whereNotIn('status', [
                    \App\Modules\Pm\States\Project\Suspended::class,
                ]))
                ->count();
            
            // 审核人任务计数（排除已暂停项目中的任务）
            $myOverdueAuditCount = \App\Modules\Pm\Models\Task::where('auditor_id', $currentUser->id)
                ->where('status', \App\Modules\Pm\States\Task\Overdue::class)
                ->whereHas('project', fn($q) => $q->whereNotIn('status', [
                    \App\Modules\Pm\States\Project\Suspended::class,
                ]))
                ->count();
            $myInProgressAuditCount = \App\Modules\Pm\Models\Task::where('auditor_id', $currentUser->id)
                ->whereIn('status', [
                    \App\Modules\Pm\States\Task\Pending::class,
                    \App\Modules\Pm\States\Task\Rejected::class,
                    \App\Modules\Pm\States\Task\InProgress::class,
                    \App\Modules\Pm\States\Task\ForAudit::class,
                ])
                ->whereHas('project', fn($q) => $q->whereNotIn('status', [
                    \App\Modules\Pm\States\Project\Suspended::class,
                ]))
                ->count();
            $myCompletedAuditCount = \App\Modules\Pm\Models\Task::where('auditor_id', $currentUser->id)
                ->whereIn('status', [
                    \App\Modules\Pm\States\Task\Completed::class,
                    \App\Modules\Pm\States\Task\OverdueCompleted::class,
                ])
                ->whereHas('project', fn($q) => $q->whereNotIn('status', [
                    \App\Modules\Pm\States\Project\Suspended::class,
                ]))
                ->count();
            $mySuspendedAuditCount = \App\Modules\Pm\Models\Task::where('auditor_id', $currentUser->id)
                ->whereHas('project', fn($q) => $q->where('status', \App\Modules\Pm\States\Project\Suspended::class))->count();
            // 待审核任务入口仅统计需要审核人处理的待审核、已逾期任务。
            $myPendingAuditTaskCount = \App\Modules\Pm\Models\Task::where('auditor_id', $currentUser->id)
                ->whereIn('status', [
                    \App\Modules\Pm\States\Task\ForAudit::class,
                    \App\Modules\Pm\States\Task\Overdue::class,
                ])
                ->whereHas('project', fn($q) => $q->whereNotIn('status', [
                    \App\Modules\Pm\States\Project\Suspended::class,
                ]))
                ->count();
            
            // 项目创建人/项目总经理的待审核延期申请数量
            $pendingExtensionCount = \App\Modules\Pm\Models\ExtensionApplication::where('approver_id', $currentUser->id)
                ->where('status', 'pending')
                ->count();
        }
    @endphp
    
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-2 d-none d-md-block sidebar" aria-label="主导航">
                <div class="sidebar-sticky">
                    <div class="p-4">
                        <h3 class="text-white text-center">产品研发管理系统</h3>
                        <p class="mb-0" style="color: #aaa;">研发全生命周期管理</p>
                    </div>
                    @include('pm::layouts.partials.sidebar-menu')
                </div>
            </nav>
            
            <main role="main" class="col-md-10 ms-sm-auto px-4">
                <nav class="navbar navbar-expand-lg navbar-light">
                    <div class="container-fluid">
                        <!-- 移动端汉堡菜单按钮 -->
                        <button class="navbar-toggler d-md-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="打开导航菜单">
                            <span class="navbar-toggler-icon"></span>
                        </button>
                        <div class="navbar-collapse justify-content-end" id="navbarNav" style="display: flex !important; flex-basis: auto;">
                            <ul class="navbar-nav">
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('pm.notification.index') }}" style="display: flex; align-items: center;">
                                        <i class="fa fa-bell" style="font-size: 18px;"></i>
                                        @php
                                            $unreadCount = 0;
                                            if (auth()->check()) {
                                                $unreadCount = \App\Modules\Pm\Models\Notification::where('user_id', auth()->id())
                                                    ->where('is_read', false)
                                                    ->count();
                                            }
                                        @endphp
                                        @if($unreadCount > 0)
                                        <span class="badge badge-danger rounded-pill ms-1" style="background-color: #dc3545; color: white; width: 20px; height: 20px; border-radius: 50%; font-size: 12px; display: inline-flex; align-items: center; justify-content: center;">{{ $unreadCount }}</span>
                                        @endif
                                    </a>
                                </li>
                                
                                {{-- 我的回收站：仅登录的非管理员显示（管理员使用左侧菜单里的统一回收站） --}}
                                @if($currentUser && !$isAdmin)
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('pm.my-trash') }}" title="我的回收站" style="display: flex; align-items: center;">
                                        <i class="fa fa-trash" style="font-size: 18px;"></i>
                                        @php
                                            $myTrashCount = 0;
                                            if (auth()->check()) {
                                                $myTrashCount = \App\Modules\Pm\Services\TrashService::countForUser(auth()->id());
                                            }
                                        @endphp
                                        <span class="badge rounded-pill ms-1" style="background-color: {{ $myTrashCount > 0 ? '#dc3545' : '#6c757d' }}; color: white; min-width: 20px; height: 20px; border-radius: 50%; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; padding: 0 5px;">{{ $myTrashCount }}</span>
                                    </a>
                                </li>
                                @endif

                                <li class="nav-item dropdown user-info">
                                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="用户中心">
                                        <div class="user-avatar">
                                            @php
                                                $currentUser = auth()->user();
                                            @endphp
                                            @if($currentUser && $currentUser->avatar)
                                                @php
                                                    $avatarPath = str_starts_with($currentUser->avatar, 'defaults/') 
                                                        ? 'avatars/' . $currentUser->avatar 
                                                        : 'storage/avatars/' . $currentUser->avatar;
                                                @endphp
                                                <img src="{{ asset($avatarPath) }}" 
                                                     alt="用户头像" 
                                                     style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                                            @else
                                                {{ mb_substr($userName, 0, 1) }}
                                            @endif
                                        </div>
                                        <div>
                                            <div class="user-name">{{ $userName }}</div>
                                            <div class="user-role">{{ $userRole }}</div>
                                        </div>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userMenu" style="min-width: 170px;">
                                        <li>
                                            <a class="dropdown-item" href="{{ route('pm.profile.edit') }}">
                                                <i class="fa fa-user me-2"></i>基本资料
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('pm.profile.password.edit') }}">
                                                <i class="fa fa-key me-2"></i>修改密码
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="{{ route('pm.profile.avatar') }}">
                                                <i class="fa fa-image me-2"></i>上传头像
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" action="{{ route('pm.logout') }}" onsubmit="return confirm('确定退出登录？')">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="fa fa-sign-out me-2"></i>退出登录
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ route('pm.profile.wecom.index') }}" title="企业微信" style="display: flex; align-items: center;">
                                        <i class="fab fa-weixin" style="font-size: 18px;"></i>
                                    </a>
                                </li>

                            </ul>
                        </div>
                    </div>
                </nav>
                
                
                @yield('content')
            </main>
        </div>
    </div>
    
    <!-- 全局消息模态弹窗 -->
    <div class="modal fade" id="globalMessageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" id="globalMessageHeader">
                    <h5 class="modal-title" id="globalMessageTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body" id="globalMessageBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" id="globalMessageBtn">确认</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 移动端 Offcanvas 侧边栏 -->
    <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel" style="background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%); color: #fff; max-width: 280px;">
        <div class="offcanvas-header border-bottom border-secondary">
            <h5 class="offcanvas-title text-white" id="mobileSidebarLabel">产品研发管理系统</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="关闭"></button>
        </div>
        <div class="offcanvas-body p-0">
            <div class="pm-mobile-menu">
                @include('pm::layouts.partials.sidebar-menu')
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/pm/js/jquery.min.js') }}"></script>
    <script src="{{ asset('assets/pm/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ asset('assets/pm/js/echarts.min.js') }}"></script>
    <!-- 文件预览库 -->
    <script src="{{ asset('assets/pm/js/pdfjs.min.js') }}"></script>
    <script src="{{ asset('assets/pm/js/mammoth.min.js') }}"></script>
    <script src="{{ asset('assets/pm/js/xlsx.full.min.js') }}"></script>
    <script>
        // 全局消息模态弹窗
        let bsModalInstance = null;

        function showGlobalModal(type, title, message) {
            const modal = document.getElementById('globalMessageModal');
            if (!modal) return;

            if (!bsModalInstance) {
                bsModalInstance = new bootstrap.Modal(modal);
            }

            const header = document.getElementById('globalMessageHeader');
            const titleEl = document.getElementById('globalMessageTitle');
            const body = document.getElementById('globalMessageBody');
            const btn = document.getElementById('globalMessageBtn');

            header.className = 'modal-header';
            btn.className = 'btn';

            if (type === 'success') {
                header.classList.add('bg-success', 'text-white');
                btn.classList.add('btn-success');
                titleEl.innerHTML = '<i class="fa fa-check-circle mr-2"></i> ' + title;
            } else if (type === 'warning') {
                header.classList.add('bg-warning', 'text-dark');
                btn.classList.add('btn-warning');
                titleEl.innerHTML = '<i class="fa fa-info-circle mr-2"></i> ' + title;
            } else {
                header.classList.add('bg-danger', 'text-white');
                btn.classList.add('btn-danger');
                titleEl.innerHTML = '<i class="fa fa-exclamation-circle mr-2"></i> ' + title;
            }

            body.innerHTML = message ? String(message).split("\n").join('<br>') : '';
            bsModalInstance.show();
        }

        // 页面加载后立即弹出错误/成功消息（不依赖 DOMContentLoaded，避免时序问题）
        (function showServerSideMessages() {
            @if(session('success'))
            showGlobalModal('success', '操作成功', @json(session('success')));
            @endif
            @if(session('error'))
            showGlobalModal('danger', '操作失败', @json(session('error')));
            @endif
            @if(session('warning'))
            showGlobalModal('warning', '提示', @json(session('warning')));
            @endif
            @php
                $_errorStr = '';
                if ($errors->has('error')) {
                    $_errorStr = $errors->first('error');
                } elseif ($errors->any()) {
                    $_errorStr = implode("\n", $errors->all());
                }
            @endphp
            @if($_errorStr !== '')
            showGlobalModal('danger', @json($errors->has('error') ? '操作失败' : '表单验证失败'), @json($_errorStr));
            @endif
        })();
    </script>
    @yield('scripts')
</body>
</html>

