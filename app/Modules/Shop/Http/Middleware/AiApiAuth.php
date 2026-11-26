<?php

namespace App\Modules\Shop\Http\Middleware;

use App\Modules\Shop\Models\AiDelegationToken;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI 外部数据 API 鉴权。
 *
 * 认证：X-AI-Key（系统密钥，.env 的 AI_API_KEY）
 * 授权：X-AI-Token（用户在「AI 授权中心」签发的委托令牌）
 *
 * 默认 fail-closed：即使 X-AI-Key 正确，读取也要求委托令牌；
 * 仅当 AI_API_ALLOW_ANONYMOUS_READ=true 时才允许匿名读取。
 */
class AiApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('ai.api.key');

        if ($expected === '') {
            return response()->json([
                'error' => 'AI_API_KEY 未配置，请在 .env 中设置 AI_API_KEY 后再调用数据 API。',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $provided = (string) ($request->header('X-AI-Key') ?: $request->query('api_key'));

        if (!hash_equals($expected, $provided)) {
            return response()->json(['error' => '未授权：API Key 无效。'], Response::HTTP_UNAUTHORIZED);
        }

        $plain = (string) ($request->header('X-AI-Token') ?: $request->query('ai_token'));
        $token = null;

        if ($plain !== '') {
            $token = AiDelegationToken::findByPlain($plain);

            if (!$token || !$token->isValid()) {
                return response()->json([
                    'error' => '未授权：委托令牌无效、已吊销或已过期。',
                ], Response::HTTP_UNAUTHORIZED);
            }
        } elseif (!config('ai.api.allow_anonymous_read', false)) {
            return response()->json([
                'error' => '未授权：本接口必须携带用户委托令牌（X-AI-Token）。'
                    . '请让授权人在「AI 授权中心」签发令牌后重试。',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($token) {
            $user = $token->user;
            Auth::setUser($user);
            $request->setUserResolver(fn () => $user);
            $request->attributes->set('ai.token', $token);
            $request->attributes->set('ai.user', $user);
            $token->touchUsed();
        }

        return $next($request);
    }
}
