<?php

namespace App\Modules\Shop\Http\Requests\Master;

use App\Modules\Shop\Rules\Barcode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $barcode = $this->input('barcode');
        $this->merge([
            'barcode' => is_string($barcode) && trim($barcode) === '' ? null : (is_string($barcode) ? trim($barcode) : $barcode),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('shop.product')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('products', 'code')->ignore($id)],
            'barcode' => ['nullable', 'string', 'max:50', new Barcode(), Rule::unique('products', 'barcode')->ignore($id)],
            'name' => ['required', 'string', 'max:100'],
            'category_id' => ['nullable', 'exists:product_categories,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'tax_id' => ['nullable', 'exists:taxes,id'],
            'cost_method' => ['required', Rule::in(['standard', 'avco', 'fifo'])],
            'standard_cost' => ['required', 'numeric', 'min:0'],
            'avg_cost' => ['nullable', 'numeric', 'min:0'],
            'list_price' => ['required', 'numeric', 'min:0'],
            'traceability' => ['required', Rule::in(['none', 'lot', 'serial'])],
            'shelf_life_days' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => '请输入商品编码',
            'code.unique' => '该商品编码已存在',
            'barcode.unique' => '该条码已被其他商品使用',
            'name.required' => '请输入商品名称',
            'category_id.exists' => '所选类别不存在',
            'unit_id.exists' => '所选单位不存在',
            'tax_id.exists' => '所选税率不存在',
            'standard_cost.required' => '请输入标准成本',
            'standard_cost.min' => '标准成本不能小于 0',
            'list_price.required' => '请输入零售价',
            'list_price.min' => '零售价不能小于 0',
            'traceability.required' => '请选择追溯方式',
            'image.image' => '请上传有效的图片文件',
            'image.mimes' => '商品图片只支持 JPG、PNG、WebP 格式',
            'image.max' => '商品图片不能超过 5 MB',
            'image.dimensions' => '商品图片最长边不能超过 6000 像素',
            'image.uploaded' => '图片上传失败，请重试或更换文件',
        ];
    }
}
