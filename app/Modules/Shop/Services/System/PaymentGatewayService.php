<?php

namespace App\Modules\Shop\Services\System;

use App\Modules\Shop\Models\PaymentGatewaySetting;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * 微信支付 / 支付宝接入资料登记与配置检查。
 *
 * 只做资料完整度、密钥格式和网关连通性检查，不发起下单或扣款。
 */
class PaymentGatewayService
{
    private const SECRET_FIELDS = ['api_secret', 'private_key', 'public_key'];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function providers(): array
    {
        $configs = config('payment_gateways.providers', []);
        $settings = PaymentGatewaySetting::query()
            ->whereIn('provider', array_keys($configs))
            ->get()
            ->keyBy('provider');

        $result = [];
        foreach ($configs as $key => $meta) {
            $setting = $settings->get($key) ?: new PaymentGatewaySetting([
                'provider' => $key,
                'gateway_url' => $meta['gateway_url'] ?? null,
            ]);

            $missing = $this->missingFields($setting, $meta);
            $status = $this->status($setting, $missing);

            $result[$key] = array_merge($meta, [
                'key' => $key,
                'setting' => $setting,
                'missing' => $missing,
                'required_total' => count($meta['required'] ?? []),
                'required_done' => count($meta['required'] ?? []) - count($missing),
                'status_code' => $status['code'],
                'status_label' => $status['label'],
                'status_tone' => $status['tone'],
            ]);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(string $provider): array
    {
        $meta = config('payment_gateways.providers.' . $provider);
        abort_if(! is_array($meta), 404);

        return $meta;
    }

    public function find(string $provider): PaymentGatewaySetting
    {
        $meta = $this->meta($provider);

        return PaymentGatewaySetting::query()->firstOrNew(
            ['provider' => $provider],
            ['gateway_url' => $meta['gateway_url'] ?? null]
        );
    }

    /**
     * 保存接入资料；密钥字段留空表示保留原值。
     *
     * @param  array<string, mixed>  $data
     */
    public function save(string $provider, array $data): PaymentGatewaySetting
    {
        $meta = $this->meta($provider);
        $setting = $this->find($provider);

        $oldSummary = $this->safeSummary($setting);

        foreach (['app_id', 'merchant_id', 'api_secret', 'private_key', 'public_key', 'cert_serial', 'gateway_url'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];
            if (in_array($field, self::SECRET_FIELDS, true) && blank($value) && $setting->exists) {
                continue; // 已保存的密钥不允许被空值覆盖
            }

            $setting->{$field} = filled($value) ? $value : null;
        }

        if (blank($setting->gateway_url)) {
            $setting->gateway_url = $meta['gateway_url'] ?? null;
        }

        // 资料一变更，旧检测结论即失效
        $setting->last_checked_at = null;
        $setting->last_check_status = null;
        $setting->last_check_message = null;
        $setting->save();

        $newSummary = $this->safeSummary($setting);
        $this->audit->record([
            'module' => 'system',
            'action' => \App\Modules\Shop\Models\AuditLog::ACTION_UPDATE,
            'model' => PaymentGatewaySetting::class,
            'record_id' => $setting->id,
            'document_no' => 'PAY-GATEWAY-' . strtoupper($provider),
            'field' => 'provider',
            'old_value' => $oldSummary,
            'new_value' => $newSummary,
            'message' => ($meta['name'] ?? $provider) . '接入资料已保存，等待连接检测',
        ]);

        return $setting;
    }

    /**
     * 检查已保存资料：必填项、密钥格式、网关域名与网络连通性。
     *
     * @return array{ok:bool, message:string}
     */
    public function check(string $provider): array
    {
        $meta = $this->meta($provider);
        $setting = $this->find($provider);

        $missing = $this->missingFields($setting, $meta);
        if ($missing !== []) {
            return $this->markCheck($setting, false, '缺少必填资料：' . implode('、', $missing));
        }

        if ($setting->hasSecret('private_key') && $this->parsePrivateKey((string) $setting->private_key) === null) {
            return $this->markCheck($setting, false, '应用/商户私钥格式无法解析，请粘贴完整的 PEM 或纯 Base64 私钥');
        }

        if ($setting->hasSecret('public_key') && $this->parsePublicKey((string) $setting->public_key) === null) {
            return $this->markCheck($setting, false, '平台公钥格式无法解析，请粘贴完整公钥或纯 Base64');
        }

        $gateway = (string) $setting->gateway_url;
        if (! str_starts_with(strtolower($gateway), 'https://')) {
            return $this->markCheck($setting, false, '网关地址必须使用 https://');
        }

        $host = strtolower((string) parse_url($gateway, PHP_URL_HOST));
        $allowed = array_map('strtolower', $meta['gateway_hosts'] ?? []);
        if ($host === '' || ! in_array($host, $allowed, true)) {
            return $this->markCheck($setting, false, '网关地址不是官方域名，请恢复为 ' . ($meta['gateway_url'] ?? '默认地址'));
        }

        try {
            $response = Http::connectTimeout(5)
                ->timeout(8)
                ->withHeaders(['User-Agent' => 'erp-shop-payment-check/1.0'])
                ->get($gateway);

            if ($response->status() < 100 || $response->status() >= 600) {
                return $this->markCheck($setting, false, '网关返回异常状态，暂时无法确认连通性');
            }

            return $this->markCheck(
                $setting,
                true,
                '资料完整、密钥格式有效，已连通 ' . $host . '（HTTP ' . $response->status() . '）。真实扣款还需完成 API 下单与回调验签。'
            );
        } catch (Throwable $e) {
            return $this->markCheck($setting, false, '网关连接失败：' . mb_substr($e->getMessage(), 0, 120));
        }
    }

    /**
     * 首启概览：已通过检测的通道数 / 总通道数。
     *
     * @return array{ready:int, total:int, configured:int, pending:bool}
     */
    public function summary(): array
    {
        $configs = config('payment_gateways.providers', []);
        $total = count($configs);

        $ready = PaymentGatewaySetting::query()
            ->whereIn('provider', array_keys($configs))
            ->where('last_check_status', PaymentGatewaySetting::STATUS_PASS)
            ->count();

        $configured = PaymentGatewaySetting::query()
            ->whereIn('provider', array_keys($configs))
            ->count();

        return [
            'ready' => $ready,
            'total' => $total,
            'configured' => $configured,
            'pending' => $ready < $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    private function missingFields(PaymentGatewaySetting $setting, array $meta): array
    {
        $missing = [];
        foreach ($meta['required'] ?? [] as $field) {
            if (blank($setting->getAttribute($field))) {
                $missing[] = $this->fieldLabel($field, $meta);
            }
        }

        return $missing;
    }

    private function fieldLabel(string $field, array $meta): string
    {
        return match ($field) {
            'app_id' => $meta['app_label'] ?? 'APPID',
            'merchant_id' => $meta['merchant_label'] ?? '商户号',
            'api_secret' => $meta['secret_label'] ?? '接口密钥',
            'private_key' => $meta['private_key_label'] ?? '应用私钥',
            'public_key' => $meta['public_key_label'] ?? '平台公钥',
            'cert_serial' => $meta['cert_serial_label'] ?? '证书序列号',
            default => $field,
        };
    }

    /**
     * @param  list<string>  $missing
     * @return array{code:string, label:string, tone:string}
     */
    private function status(PaymentGatewaySetting $setting, array $missing): array
    {
        if (! $setting->exists) {
            return ['code' => 'empty', 'label' => '未配置', 'tone' => 'secondary'];
        }

        if ($setting->last_check_status === PaymentGatewaySetting::STATUS_PASS && $missing === []) {
            return ['code' => 'pass', 'label' => '资料完整', 'tone' => 'success'];
        }

        if ($setting->last_check_status === PaymentGatewaySetting::STATUS_FAIL) {
            return ['code' => 'fail', 'label' => '检测未通过', 'tone' => 'danger'];
        }

        return $missing === []
            ? ['code' => 'draft', 'label' => '待检测', 'tone' => 'warning']
            : ['code' => 'incomplete', 'label' => '待补全', 'tone' => 'warning'];
    }

    /**
     * @return array{ok:bool, message:string}
     */
    private function markCheck(PaymentGatewaySetting $setting, bool $ok, string $message): array
    {
        $setting->forceFill([
            'last_checked_at' => Carbon::now(),
            'last_check_status' => $ok ? PaymentGatewaySetting::STATUS_PASS : PaymentGatewaySetting::STATUS_FAIL,
            'last_check_message' => mb_substr($message, 0, 255),
        ])->save();

        return ['ok' => $ok, 'message' => $message];
    }

    /**
     * 审计快照不含任何密钥明文。
     *
     * @return array<string, mixed>
     */
    private function safeSummary(PaymentGatewaySetting $setting): array
    {
        return [
            'provider' => $setting->provider,
            'app_id' => $setting->app_id,
            'merchant_id' => $setting->merchant_id,
            'has_api_secret' => $setting->hasSecret('api_secret'),
            'has_private_key' => $setting->hasSecret('private_key'),
            'has_public_key' => $setting->hasSecret('public_key'),
            'cert_serial' => $setting->cert_serial,
            'gateway_url' => $setting->gateway_url,
            'fields' => Arr::only($setting->getAttributes(), ['app_id', 'merchant_id', 'cert_serial', 'gateway_url']),
        ];
    }

    private function parsePrivateKey(string $value): mixed
    {
        $candidates = str_contains($value, 'BEGIN')
            ? [$value]
            : [$this->wrapPem($value, 'PRIVATE KEY'), $this->wrapPem($value, 'RSA PRIVATE KEY')];

        foreach ($candidates as $candidate) {
            $key = @openssl_pkey_get_private($candidate);
            if ($key !== false) {
                return $key;
            }
        }

        return null;
    }

    private function parsePublicKey(string $value): mixed
    {
        $candidates = str_contains($value, 'BEGIN')
            ? [$value]
            : [$this->wrapPem($value, 'PUBLIC KEY'), $this->wrapPem($value, 'RSA PUBLIC KEY')];

        foreach ($candidates as $candidate) {
            $key = @openssl_pkey_get_public($candidate);
            if ($key !== false) {
                return $key;
            }
        }

        return null;
    }

    private function wrapPem(string $body, string $label): string
    {
        $clean = preg_replace('/\s+/', '', $body) ?? '';

        return "-----BEGIN {$label}-----\n"
            . chunk_split($clean, 64, "\n")
            . "-----END {$label}-----\n";
    }
}
