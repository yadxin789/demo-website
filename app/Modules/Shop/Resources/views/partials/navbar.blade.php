<header class="app-topbar">
    <button class="btn btn-outline-secondary d-lg-none" type="button" aria-label="切换菜单">
        <i class="fa-solid fa-bars"></i>
    </button>
    <div class="flex-grow-1">
        @yield('heading', '百货零售管理系统')
    </div>
    <div class="d-flex align-items-center gap-2">
        @auth
            @php
                $currentUser = auth()->user();
                $roleLabel = $currentUser->roles->first()->name ?? '未分配角色';
                $notificationService = app(\App\Modules\Shop\Services\NotificationService::class);
                $unreadNotifications = $notificationService->unreadCount($currentUser);
                $recentNotifications = $notificationService->recent($currentUser);
            @endphp
            <div class="dropdown notification-menu">
                <button class="app-icon-button" type="button" id="notificationBell"
                        data-bs-toggle="dropdown" aria-expanded="false" aria-label="消息通知">
                    <i class="fa-regular fa-bell"></i>
                    @if ($unreadNotifications > 0)
                        <span class="notification-badge">{{ $unreadNotifications > 99 ? '99+' : $unreadNotifications }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationBell">
                    <div class="notification-panel-head">
                        <span class="fw-semibold">消息通知</span>
                        <span class="small text-muted">{{ $unreadNotifications }} 条未读</span>
                    </div>
                    @forelse ($recentNotifications as $notice)
                        <a class="notification-preview {{ $notice->is_read ? '' : 'is-unread' }}"
                           href="{{ route('shop.notification.open', $notice) }}">
                            <span class="notification-icon level-{{ $notice->level }}">
                                <i class="fa-solid {{ $notice->icon() }}"></i>
                            </span>
                            <span class="notification-preview-body">
                                <span class="notification-preview-title">{{ $notice->title }}</span>
                                <span class="notification-preview-text">{{ \Illuminate\Support\Str::limit($notice->content, 40) }}</span>
                                <span class="notification-preview-time">{{ $notice->created_at->diffForHumans() }}</span>
                            </span>
                        </a>
                    @empty
                        <div class="notification-empty">
                            <i class="fa-regular fa-bell-slash"></i>
                            <span>暂无消息</span>
                        </div>
                    @endforelse
                    <div class="notification-panel-foot">
                        <a href="{{ route('shop.notification.index') }}">查看全部</a>
                        @if ($unreadNotifications > 0)
                            <form method="post" action="{{ route('shop.notification.mark-all-read') }}">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm p-0">全部已读</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
            <div class="dropdown user-menu">
                <button class="app-user-toggle dropdown-toggle d-flex align-items-center gap-2"
                        type="button" id="userMenu" data-bs-toggle="dropdown"
                        aria-expanded="false" aria-label="用户中心">
                    @include('shop::profile.partials.avatar-preview', ['user' => $currentUser, 'size' => '2.25rem'])
                    <span class="user-meta d-none d-sm-flex flex-column text-start">
                        <span class="user-name">{{ $currentUser->name }}</span>
                        <span class="user-role">{{ $roleLabel }}</span>
                    </span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                    <li>
                        <a class="dropdown-item" href="{{ route('shop.profile.edit') }}">
                            <i class="fa-solid fa-user me-2"></i>个人资料
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('shop.profile.avatar') }}">
                            <i class="fa-solid fa-image me-2"></i>更新头像
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('shop.password.show') }}">
                            <i class="fa-solid fa-key me-2"></i>重置密码
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="post" action="{{ route('shop.logout') }}" onsubmit="return confirm('确定退出登录？')">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="fa-solid fa-right-from-bracket me-2"></i>退出登录
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        @else
            <span class="text-muted small">未登录</span>
        @endauth
    </div>
</header>
