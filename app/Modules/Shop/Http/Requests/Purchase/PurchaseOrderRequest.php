<?php

namespace App\Modules\Shop\Http\Requests\Purchase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'order_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.tax_id' => ['nullable', 'exists:taxes,id'],
            'lines.*.line_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => '请选择供应商',
            'supplier_id.exists' => '所选供应商不存在',
            'warehouse_id.required' => '请选择入库仓库',
            'lines.required' => '请至少添加一行商品',
            'lines.*.product_id.required' => '请选择商品',
            'lines.*.qty.required' => '请输入采购数量',
            'lines.*.qty.min' => '采购数量必须大于 0',
            'lines.*.unit_price.required' => '请输入采购单价',
            'lines.*.discount_rate.max' => '折扣率不能超过 100',
        ];
    }
}
