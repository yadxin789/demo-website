<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Modules\Shop\Models\Notification;
use App\Modules\Shop\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 个人站内消息中心：仅能查看/操作自己的消息。
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->string('filter')->toString() === 'unread' ? 'unread' : 'all';

        $notifications = $user->notifications()
            ->when($filter === 'unread', fn ($query) => $query->where('is_read', false))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('shop::notification.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $this->notifications->unreadCount($user),
        ]);
    }

    /**
     * 从顶栏点开一条消息：标记已读并跳到相关业务页。
     */
    public function open(Request $request, Notification $notification): RedirectResponse
    {
        $this->authorizeOwn($request, $notification);
        $notification->markAsRead();

        return redirect()->to($notification->actionUrl() ?? route('shop.notification.index'));
    }

    public function markAsRead(Request $request, Notification $notification): RedirectResponse
    {
        $this->authorizeOwn($request, $notification);
        $notification->markAsRead();

        return back()->with('status', '消息已标记为已读');
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $count = $this->notifications->markAllAsRead($request->user());

        return back()->with('status', $count > 0 ? "已将 {$count} 条消息标记为已读" : '没有未读消息');
    }

    public function destroy(Request $request, Notification $notification): RedirectResponse
    {
        $this->authorizeOwn($request, $notification);
        $notification->delete();

        return back()->with('status', '消息已删除');
    }

    private function authorizeOwn(Request $request, Notification $notification): void
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403, '无权操作该消息');
    }
}
