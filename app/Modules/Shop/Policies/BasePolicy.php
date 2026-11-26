<?php

namespace App\Modules\Shop\Policies;

use App\Modules\Shop\Models\User;

abstract class BasePolicy
{
    /**
     * 超级管理员角色直通所有策略检查。
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->roles()->where('code', 'super-admin')->exists() ? true : null;
    }
}
