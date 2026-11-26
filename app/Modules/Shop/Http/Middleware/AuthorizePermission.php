<?php

namespace App\Modules\Shop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthorizePermission
{
    /**
     * 按权限 code 鉴权；超级管理员角色（super-admin）直通。
     */
    public function handle(Request $request, Closure $next, string $code): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, '未登录或无权限');
        }

        $isSuperAdmin = $user->roles()->where('code', 'super-admin')->exists();

        if (! $isSuperAdmin && ! $user->hasPermission($code)) {
            abort(403, '您没有访问该功能的权限');
        }

        return $next($request);
    }
}
