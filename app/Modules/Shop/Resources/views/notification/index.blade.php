@extends('shop::layouts.app')

@section('title', '通知中心')
@section('heading', '通知中心')

@section('content')
    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="btn-group" role="group" aria-label="消息筛选">
            <a href="{{ route('shop.notification.index') }}"
               class="btn btn-sm {{ $filter === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}">全部</a>
            <a href="{{ route('shop.notification.index', ['filter' => 'unread']) }}"
               class="btn btn-sm {{ $filter === 'unread' ? 'btn-primary' : 'btn-outline-secondary' }}">
                未读 @if ($unreadCount > 0)<span class="badge text-bg-danger ms-1">{{ $unreadCount }}</span>@endif
            </a>
        </div>
        @if ($unreadCount > 0)
            <form method="post" action="{{ route('shop.notification.mark-all-read') }}">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-check-double me-1"></i>全部标为已读
                </button>
            </form>
        @endif
    </div>

    <div class="card">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notice)
                <div class="list-group-item notification-row {{ $notice->is_read ? '' : 'is-unread' }}">
                    <span class="notification-icon level-{{ $notice->level }}">
                        <i class="fa-solid {{ $notice->icon() }}"></i>
                    </span>
                    <div class="notification-row-body">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <span class="notification-row-title">{{ $notice->title }}</span>
                            <span class="notification-level level-{{ $notice->level }}">{{ $notice->levelLabel() }}</span>
                            @if (! $notice->is_read)
                                <span class="notification-unread-dot" title="未读"></span>
                            @endif
                        </div>
                        @if ($notice->content)
                            <div class="notification-row-text">{{ $notice->content }}</div>
                        @endif
                        <div class="notification-row-meta">
                            <span>{{ $notice->created_at->format('Y-m-d H:i') }}</span>
                            <span>{{ $notice->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <div class="notification-row-actions">
                        @if ($notice->actionUrl())
                            <a href="{{ route('shop.notification.open', $notice) }}" class="btn btn-sm btn-outline-secondary">
                                查看
                            </a>
                        @endif
                        @if (! $notice->is_read)
                            <form method="post" action="{{ route('shop.notification.mark-read', $notice) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-link p-0">标为已读</button>
                            </form>
                        @endif
                        <form method="post" action="{{ route('shop.notification.destroy', $notice) }}"
                              onsubmit="return confirm('确定删除这条消息？')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-link text-danger p-0">删除</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="notification-empty notification-empty-page">
                    <i class="fa-regular fa-bell-slash"></i>
                    <span>{{ $filter === 'unread' ? '没有未读消息' : '暂无消息' }}</span>
                </div>
            @endforelse
        </div>
    </div>

    @if ($notifications->hasPages())
        <div class="mt-3">{{ $notifications->links() }}</div>
    @endif
@endsection
