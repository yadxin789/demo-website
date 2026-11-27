<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('mes')->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_material_id' => ['required', 'integer', Rule::exists('mes.materials', 'id')],
            'plan_qty' => ['required', 'numeric', 'min:0.001', 'max:999999'],
            'plan_start' => ['nullable', 'date'],
            'plan_end' => ['nullable', 'date', 'after_or_equal:plan_start'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:9'],
            'customer' => ['nullable', 'string', 'max:64'],
            'remark' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'plan_qty.min' => '数量必须大于 0。',
            'plan_end.after_or_equal' => '计划完工不能早于计划开工。',
            'priority.min' => '优先级填 1~9，1 最急。',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'product_material_id' => '产品',
            'plan_qty' => '计划数量',
            'plan_start' => '计划开工',
            'plan_end' => '计划完工',
            'priority' => '优先级',
            'customer' => '客户',
            'remark' => '备注',
        ];
    }
}
