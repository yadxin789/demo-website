<?php

namespace App\Modules\Shop\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SequenceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'prefix' => $this->input('prefix') ?? '',
            'suffix' => $this->input('suffix') ?? '',
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'prefix' => ['nullable', 'string', 'max:20'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'padding' => ['required', 'integer', 'min:1', 'max:10'],
            'period_mode' => ['required', Rule::in(['none', 'day', 'month', 'year'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '请输入规则名称',
            'padding.required' => '请输入流水位数',
            'padding.min' => '流水位数至少 1 位',
            'padding.max' => '流水位数不能超过 10 位',
        ];
    }
}
