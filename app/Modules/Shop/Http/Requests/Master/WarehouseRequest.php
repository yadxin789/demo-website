<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WarehouseRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('shop.warehouse')?->id;

        return [
            'name' => ['required', 'string', 'max:50'],
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($id)],
            'address' => ['nullable', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '请输入仓库名称',
            'code.required' => '请输入仓库编码',
            'code.unique' => '该仓库编码已存在',
        ];
    }
}
