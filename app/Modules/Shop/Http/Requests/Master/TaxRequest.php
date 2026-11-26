<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('shop.tax')?->id;

        return [
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20', Rule::unique('taxes', 'code')->ignore($id)],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'type' => ['required', Rule::in(['exclusive', 'inclusive'])],
            'account_id' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '请输入税率名称',
            'code.required' => '请输入税率编码',
            'code.unique' => '该税率编码已存在',
            'rate.required' => '请输入税率',
            'rate.numeric' => '税率必须为数字',
            'rate.min' => '税率不能小于 0',
            'rate.max' => '税率不能超过 100',
            'type.required' => '请选择计税方式',
        ];
    }
}
