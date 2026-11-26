<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('shop.unit')?->id;

        return [
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20', Rule::unique('units', 'code')->ignore($id)],
            'precision' => ['required', 'integer', 'min:0', 'max:4'],
            'is_base' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '请输入单位名称',
            'code.required' => '请输入单位编码',
            'code.unique' => '该单位编码已存在',
            'precision.required' => '请输入小数位',
            'precision.max' => '小数位不能超过 4 位',
        ];
    }
}
