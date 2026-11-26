<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 微信支付 / 支付宝接入资料。
 *
 * 密钥类字段使用 Laravel encrypted cast，数据库只保存密文，页面不回显明文。
 */
class PaymentGatewaySetting extends BaseModel
{
    public const STATUS_PASS = 'pass';
    public const STATUS_FAIL = 'fail';

    protected $fillable = [
        'provider',
        'app_id',
        'merchant_id',
        'api_secret',
        'private_key',
        'public_key',
        'cert_serial',
        'gateway_url',
        'last_checked_at',
        'last_check_status',
        'last_check_message',
    ];

    protected function casts(): array
    {
        return [
            'api_secret' => 'encrypted',
            'private_key' => 'encrypted',
            'public_key' => 'encrypted',
            'last_checked_at' => 'datetime',
        ];
    }

    public function hasSecret(string $field): bool
    {
        return filled($this->getAttribute($field));
    }

    public function checkedLabel(): ?string
    {
        return $this->last_checked_at?->format('Y-m-d H:i');
    }
}
