<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('shop.supplier')?->id;

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('suppliers', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'contact_name' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'tax_no' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => '请输入供应商编码',
            'code.unique' => '该供应商编码已存在',
            'name.required' => '请输入供应商名称',
            'email.email' => '邮箱格式不正确',
        ];
    }
}
