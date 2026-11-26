<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('shop.category')?->id;

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('product_categories', 'id')],
            'code' => ['required', 'string', 'max:50', Rule::unique('product_categories', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => '请输入类别编码',
            'code.unique' => '该类别编码已存在',
            'name.required' => '请输入类别名称',
            'parent_id.exists' => '所选上级类别不存在',
        ];
    }
}
