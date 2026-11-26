<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentMethodRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('shop.payment_method')?->id;

        return [
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20', Rule::unique('payment_methods', 'code')->ignore($id)],
            'type' => ['required', Rule::in(['cash', 'bank', 'electronic', 'card', 'coupon'])],
            'account_id' => ['nullable', 'integer'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '请输入支付方式名称',
            'code.required' => '请输入支付方式编码',
            'code.unique' => '该支付方式编码已存在',
            'type.required' => '请选择支付类型',
        ];
    }
}
