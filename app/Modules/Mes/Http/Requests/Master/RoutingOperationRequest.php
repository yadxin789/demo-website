<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoutingOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('mes')->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'op_no' => ['required', 'integer', 'min:1', 'max:999'],
            'op_name' => ['required', 'string', 'max:64'],
            'work_center_id' => ['nullable', 'integer', Rule::exists('mes.work_centers', 'id')],
            'std_minutes' => ['required', 'numeric', 'min:0', 'max:10000'],
            'inspection_required' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'op_no.min' => '工序号从 1 开始，习惯按 10 / 20 / 30 排。',
            'std_minutes.min' => '标准工时不能是负数。',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'op_no' => '工序号',
            'op_name' => '工序名称',
            'work_center_id' => '工作中心',
            'std_minutes' => '标准工时',
            'inspection_required' => '需检验',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'inspection_required' => $this->boolean('inspection_required'),
            'work_center_id' => $this->input('work_center_id') ?: null,
        ]);
    }
}
