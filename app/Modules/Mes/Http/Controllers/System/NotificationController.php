<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\System;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 系统 · 站内通知（M6-04）。
 *
 * 演示版不启用消息推送（MC-05 无定时任务、无队列常驻），此页只读取
 * notifications 表做留档展示；空库时给出说明，不伪造数据。
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin();
        $rows = DB::connection('mes')->table('notifications')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($row) {
                $data = json_decode((string) $row->data, true) ?: [];

                return (object) [
                    'id' => $row->id,
                    'title' => $data['title'] ?? ($data['message'] ?? '通知'),
                    'body' => $data['message'] ?? '',
                    'notifiable' => $this->userName($row->notifiable_id),
                    'read' => $row->read_at !== null,
                    'at' => $row->created_at,
                ];
            });

        return view('mes::system.notifications.index', [
            'rows' => $rows,
            'unread' => $rows->where('read', false)->count(),
        ]);
    }

    private function userName($id): string
    {
        static $cache = [];
        $id = (int) $id;
        if (! isset($cache[$id])) {
            $cache[$id] = User::query()->whereKey($id)->value('name') ?? ('#'.$id);
        }

        return $cache[$id];
    }

    private function ensureAdmin(): void
    {
        if (! auth('mes')->user()?->hasRole('admin')) {
            abort(403, '系统页只对系统管理员开放。');
        }
    }
}
