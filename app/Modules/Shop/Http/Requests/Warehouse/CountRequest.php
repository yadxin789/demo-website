<?php

namespace App\Modules\Shop\Http\Requests\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class CountRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'count_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => '请选择盘点仓库',
            'warehouse_id.exists' => '所选仓库不存在',
            'location_id.exists' => '所选库位不存在',
            'count_date.date' => '盘点日期格式不正确',
        ];
    }
}
