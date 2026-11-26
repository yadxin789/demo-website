<?php

namespace App\Modules\Shop\Policies;

use App\Modules\Shop\Models\PosSession;
use App\Modules\Shop\Models\User;

class PosSessionPolicy extends BasePolicy
{
    /**
     * 查看当班会话：仅本班收银员本人；super-admin 由 before 直通。
     */
    public function viewSession(User $user, PosSession $session): bool
    {
        return $user->id === $session->cashier_id;
    }

    /**
     * 操作当班会话（交班/查看明细）：仅本班收银员本人。
     */
    public function operateSession(User $user, PosSession $session): bool
    {
        return $user->id === $session->cashier_id;
    }
}
