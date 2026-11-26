<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('shop.customer')?->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('customers', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_no' => ['nullable', 'string', 'max:30'],
            'membership_level' => ['required', Rule::in(['normal', 'silver', 'gold'])],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => '请输入客户编码',
            'code.unique' => '该客户编码已存在',
            'name.required' => '请输入客户名称',
            'email.email' => '邮箱格式不正确',
            'credit_limit.numeric' => '信用额度必须为数字',
            'credit_limit.min' => '信用额度不能小于 0',
        ];
    }
}
