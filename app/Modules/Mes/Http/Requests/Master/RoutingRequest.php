<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoutingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('mes')->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('routing')?->id;

        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^RT-[A-Z]{2,3}-\d{3}$/', Rule::unique('mes.routings', 'code')->ignore($id)],
            'product_material_id' => ['required', 'integer', Rule::exists('mes.materials', 'id')],
            'version' => ['required', 'string', 'max:16', 'regex:/^V\d+$/'],
            'status' => ['required', Rule::in(['active', 'archived'])],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => '工艺路线编码格式为 RT-XX-001。',
            'code.unique' => '这个工艺路线编码已经存在。',
            'version.regex' => '版本号写成 V1 / V2 这种形式。',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => '工艺路线编码',
            'product_material_id' => '产品物料',
            'version' => '版本',
            'status' => '状态',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'version' => strtoupper(trim((string) $this->input('version', 'V1'))),
            'status' => $this->input('status') ?: 'active',
        ]);
    }
}
