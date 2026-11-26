<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 物料新增/编辑校验（M1-06）。
 *
 * 编码规则在客户端与服务端都校验：MJ- 前缀 + 3 位序号（MC-02）。
 */
class MaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('material')?->id;

        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^MJ-[A-Z]{2}-\d{3}$/', "unique:mes.materials,code,{$id}"],
            'name' => ['required', 'string', 'max:120'],
            'spec' => ['nullable', 'string', 'max:120'],
            'unit' => ['required', 'string', 'max:16'],
            'category' => ['required', 'in:raw,semi,finished'],
            'safety_stock' => ['nullable', 'numeric', 'min:0'],
            'stock_qty' => ['nullable', 'numeric', 'min:0'],
            'std_cost' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => '物料编码',
            'name' => '物料名称',
            'unit' => '单位',
            'category' => '分类',
            'safety_stock' => '安全库存',
            'stock_qty' => '现有库存',
            'std_cost' => '标准成本',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => '编码格式必须是 MJ-原料/半成品/成品 缩写 + 3 位序号，例如 MJ-FG-001。',
            'code.unique' => '这个物料编码已经存在。',
        ];
    }
}
