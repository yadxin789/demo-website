<?php

namespace App\Modules\Shop\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditContext
{
    /**
     * 注入操作上下文（操作人 / IP / UA / 会话），供审计写入器使用。
     * 未登录时 user_id 为空，业务审计由后续服务层补充。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = (object) [
            'user_id' => $request->user()?->id,
            'username' => $request->user()?->username,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => $request->session()->getId(),
        ];

        $request->attributes->set('audit_context', $context);
        app()->instance('audit.context', $context);

        return $next($request);
    }
}
