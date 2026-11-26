<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('shop.location')?->id;
        $warehouseId = $this->input('warehouse_id');

        return [
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'parent_id' => [
                'nullable', 'exists:locations,id',
                Rule::notIn([$id]), // 不能选自己作为父级
            ],
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('locations', 'code')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('warehouse_id', $warehouseId)),
            ],
            'barcode' => [
                'nullable', 'string', 'max:64',
                Rule::unique('locations', 'barcode')->ignore($id),
            ],
            'name' => ['required', 'string', 'max:50'],
            'type' => ['required', Rule::in(['storage', 'picking', 'receiving', 'shipping'])],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => '请选择所属仓库',
            'warehouse_id.exists' => '所选仓库不存在',
            'code.required' => '请输入库位编码',
            'code.unique' => '该仓库内库位编码已存在',
            'barcode.unique' => '该库位条码已被其他库位使用',
            'name.required' => '请输入库位名称',
            'parent_id.not_in' => '不能选择当前库位作为父级',
        ];
    }
}
