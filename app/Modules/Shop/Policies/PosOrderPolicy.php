<?php

namespace App\Modules\Shop\Policies;

use App\Modules\Shop\Models\PosOrder;
use App\Modules\Shop\Models\User;

class PosOrderPolicy extends BasePolicy
{
    /**
     * 查看订单：仅当班收银员本人；super-admin 由 before 直通。
     */
    public function viewOrder(User $user, PosOrder $order): bool
    {
        return $user->id === $order->cashier_id;
    }

    /**
     * 操作订单（退款/红字等）：仅当班收银员本人。
     */
    public function operateOrder(User $user, PosOrder $order): bool
    {
        return $user->id === $order->cashier_id;
    }
}
