<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Requests;

use App\Modules\Mes\Services\ReportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('mes')->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'ok_qty' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'defect_qty' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'shift_id' => ['required', 'integer', Rule::exists('mes.shifts', 'id')],
            'remark' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'minutes.max' => '单次报工工时最多 1440 分钟（一天）。',
            'shift_id.required' => '请选择班次，报工要按班次统计。',
            'shift_id.exists' => '班次不存在，请刷新页面重试。',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'ok_qty' => '合格数',
            'defect_qty' => '不良数',
            'minutes' => '工时',
            'shift_id' => '班次',
            'remark' => '备注',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ok_qty' => $this->input('ok_qty') === null || $this->input('ok_qty') === '' ? 0 : $this->input('ok_qty'),
            'defect_qty' => $this->input('defect_qty') === null || $this->input('defect_qty') === '' ? 0 : $this->input('defect_qty'),
            'minutes' => $this->input('minutes') ?: 0,
            'shift_id' => $this->input('shift_id') ?: null,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ok = (float) $this->input('ok_qty', 0);
            $ng = (float) $this->input('defect_qty', 0);

            if ($ok <= 0 && $ng <= 0) {
                $v->errors()->add('ok_qty', '合格数和不良数不能都是 0，至少填一个。');

                return;
            }

            $operation = $this->route('operation');
            if ($operation instanceof \App\Modules\Mes\Models\WorkOrderOperation) {
                $reason = app(ReportService::class)->overflowReason($operation, $ok, $ng);
                if ($reason !== null) {
                    $v->errors()->add('ok_qty', $reason);
                }
            }
        });
    }
}
