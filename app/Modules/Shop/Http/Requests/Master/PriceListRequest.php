<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PriceListRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['sale', 'purchase'])],
            'currency' => ['nullable', 'string', 'max:3'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '请输入价格表名称',
            'type.required' => '请选择价格表类型',
        ];
    }
}
