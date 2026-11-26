<?php

namespace App\Modules\Shop\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class PaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (['app_id', 'merchant_id', 'api_secret', 'private_key', 'public_key', 'cert_serial', 'gateway_url'] as $field) {
            $value = $this->input($field);
            $data[$field] = is_string($value) ? trim($value) : $value;
            if ($data[$field] === '') {
                $data[$field] = null;
            }
        }

        $this->merge($data);
    }

    public function rules(): array
    {
        return [
            'app_id' => ['nullable', 'string', 'max:80'],
            'merchant_id' => ['nullable', 'string', 'max:80'],
            'api_secret' => ['nullable', 'string', 'max:4096'],
            'private_key' => ['nullable', 'string', 'max:8192'],
            'public_key' => ['nullable', 'string', 'max:8192'],
            'cert_serial' => ['nullable', 'string', 'max:80'],
            'gateway_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'gateway_url.url' => '网关地址必须是完整的 https:// 地址',
            '*.max' => '字段内容过长，请检查是否粘贴了多余内容',
        ];
    }
}
