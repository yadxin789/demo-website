<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BomLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('mes')->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'component_material_id' => ['required', 'integer', Rule::exists('mes.materials', 'id')],
            'qty_per' => ['required', 'numeric', 'min:0.0001', 'max:99999'],
            'loss_rate' => ['nullable', 'numeric', 'min:0', 'max:0.5'],
            'seq' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'qty_per.min' => '单件用量必须大于 0。',
            'loss_rate.max' => '损耗率最多 50%，超过这个数请检查工艺。',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'component_material_id' => '组件物料',
            'qty_per' => '单件用量',
            'loss_rate' => '损耗率',
            'seq' => '行序',
        ];
    }

    protected function prepareForValidation(): void
    {
        $loss = $this->input('loss_rate');
        // 用户可能填 2 表示 2%，统一折算成 0.02；大于 1 的一律按百分数处理
        if (is_numeric($loss) && (float) $loss > 1) {
            $this->merge(['loss_rate' => round((float) $loss / 100, 4)]);
        }

        if ($this->input('loss_rate') === null || $this->input('loss_rate') === '') {
            $this->merge(['loss_rate' => 0]);
        }
    }
}
