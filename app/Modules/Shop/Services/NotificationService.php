<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Models\Notification;
use App\Modules\Shop\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * 站内消息中心：按用户落库，按角色批量投递。
 *
 * dedupeKey 会在用户维度加前缀，配合唯一索引保证同一问题每天最多提醒一次。
 */
class NotificationService
{
    public function sendToUser(
        User $user,
        string $type,
        string $level,
        string $title,
        string $content,
        ?string $actionRoute = null,
        ?string $dedupeKey = null,
    ): ?Notification {
        $key = $dedupeKey ? 'u'.$user->id.':'.$dedupeKey : null;

        if ($key !== null && Notification::where('dedupe_key', $key)->exists()) {
            return null;
        }

        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'level' => $level,
            'title' => $title,
            'content' => $content,
            'action_route' => $actionRoute,
            'dedupe_key' => $key,
        ]);
    }

    /**
     * 向一组角色下的所有启用账号投递；同一账号拥有多个目标角色时只发一条。
     *
     * @param  string|list<string>  $roles
     * @return int 实际写入条数
     */
    public function sendToRoles(
        string|array $roles,
        string $type,
        string $level,
        string $title,
        string $content,
        ?string $actionRoute = null,
        ?string $dedupeKey = null,
    ): int {
        $codes = (array) $roles;

        $users = User::query()
            ->where('status', 'active')
            ->whereHas('roles', fn ($query) => $query->whereIn('roles.code', $codes))
            ->get();

        $created = 0;
        foreach ($users as $user) {
            if ($this->sendToUser($user, $type, $level, $title, $content, $actionRoute, $dedupeKey) !== null) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function recent(User $user, int $limit = 6): Collection
    {
        return $user->notifications()
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function unreadCount(User $user): int
    {
        return $user->notifications()->where('is_read', false)->count();
    }

    public function markAllAsRead(User $user): int
    {
        return $user->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => Carbon::now()]);
    }
}
