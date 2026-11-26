<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class PriceListItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'exists:products,id'],
            'min_qty' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => '请选择商品',
            'product_id.exists' => '所选商品不存在',
            'price.required' => '请输入价格',
            'price.min' => '价格不能小于 0',
            'valid_to.after_or_equal' => '失效日期不能早于生效日期',
        ];
    }
}
