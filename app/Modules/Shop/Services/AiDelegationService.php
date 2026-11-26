<?php

namespace App\Modules\Shop\Services;

use App\Modules\Shop\Models\AiChangeProposal;
use App\Modules\Shop\Models\AiDelegationToken;
use App\Modules\Shop\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI 委托鉴权：令牌不创造额外权限，只以签发者本人的 RBAC 权限执行。
 */
class AiDelegationService
{
    /**
     * 始终读取当前请求，避免把上一请求的委托上下文带到下一次调用（测试与常驻进程尤其重要）。
     */
    private function request(): Request
    {
        return request();
    }

    public function token(): ?AiDelegationToken
    {
        return $this->request()->attributes->get('ai.token');
    }

    public function user(): ?User
    {
        return $this->request()->attributes->get('ai.user');
    }

    public function requireUser(): User
    {
        $user = $this->user();
        if (! $user) {
            abort(response()->json([
                'error' => '未授权：该操作必须由用户显式授权，请在请求头携带 X-AI-Token。',
            ], Response::HTTP_UNAUTHORIZED));
        }

        return $user;
    }

    public function requireScope(string $scope): void
    {
        $token = $this->token();
        if ($token === null) {
            return;
        }
        if (! $token->hasScope($scope)) {
            abort(response()->json([
                'error' => "未授权：当前委托令牌未授予 {$scope} 权限。",
                'required_scope' => $scope,
                'granted_scopes' => $token->scopes,
            ], Response::HTTP_FORBIDDEN));
        }
    }

    public function hasScope(string $scope): bool
    {
        return (bool) $this->token()?->hasScope($scope);
    }

    public function canReadErp(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('dashboard.view');
    }

    public function canWriteProducts(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('master.view');
    }

    public function canWritePurchase(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('purchase.view');
    }

    public function canResolveProposal(User $user, AiChangeProposal $proposal): bool
    {
        return $proposal->user_id === $user->id || $user->isAdmin();
    }
}
