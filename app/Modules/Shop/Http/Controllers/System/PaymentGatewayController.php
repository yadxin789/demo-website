<?php

namespace App\Modules\Shop\Http\Controllers\System;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Http\Requests\System\PaymentGatewayRequest;
use App\Modules\Shop\Services\System\PaymentGatewayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 微信支付 / 支付宝开通引导：官方入口、接入步骤、资料登记与连接检查。
 */
class PaymentGatewayController extends Controller
{
    public function __construct(private readonly PaymentGatewayService $gateways)
    {
    }

    public function index(Request $request): View
    {
        $providers = $this->gateways->providers();
        $active = (string) $request->query('provider', '');
        if (! isset($providers[$active])) {
            $active = array_key_first($providers) ?: 'wechat';
        }

        return view('shop::system.payment-gateways.index', [
            'providers' => $providers,
            'activeProvider' => $active,
            'summary' => $this->gateways->summary(),
        ]);
    }

    public function update(PaymentGatewayRequest $request, string $provider): RedirectResponse
    {
        $meta = $this->gateways->meta($provider);

        $this->gateways->save($provider, $request->validated());

        if ($request->boolean('check')) {
            $result = $this->gateways->check($provider);

            return redirect()
                ->route('shop.payment-gateways.index', ['provider' => $provider])
                ->with($result['ok'] ? 'status' : 'error', $result['message']);
        }

        return redirect()
            ->route('shop.payment-gateways.index', ['provider' => $provider])
            ->with('status', ($meta['name'] ?? '支付通道') . '接入资料已保存，请点击“检测配置”验证。');
    }

    public function check(string $provider): RedirectResponse
    {
        $result = $this->gateways->check($provider);

        return redirect()
            ->route('shop.payment-gateways.index', ['provider' => $provider])
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }
}
