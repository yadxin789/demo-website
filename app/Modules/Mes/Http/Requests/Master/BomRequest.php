<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Requests\Master;

use App\Modules\Mes\Models\Material;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('mes')->check();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $id = $this->route('bom')?->id;

        return [
            'code' => ['required', 'string', 'max:32', 'regex:/^BOM-[A-Z]{2,3}-\d{3}$/', Rule::unique('mes.boms', 'code')->ignore($id)],
            'product_material_id' => ['required', 'integer', Rule::exists('mes.materials', 'id')],
            'version' => ['required', 'string', 'max:16', 'regex:/^V\d+$/'],
            'status' => ['required', Rule::in(['active', 'archived'])],
            'remark' => ['nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.regex' => 'BOM 编码格式为 BOM-XX-001（两位产品类别 + 三位序号）。',
            'code.unique' => '这个 BOM 编码已经被占用了。',
            'version.regex' => '版本号写成 V1 / V2 这种形式。',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'code' => 'BOM 编码',
            'product_material_id' => '产品物料',
            'version' => '版本',
            'status' => '状态',
            'remark' => '备注',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'version' => strtoupper(trim((string) $this->input('version', 'V1'))),
            'status' => $this->input('status') ?: 'active',
        ]);

        // 产品只能是成品或半成品，原料没有 BOM
        $pid = (int) $this->input('product_material_id');
        if ($pid > 0) {
            $cat = Material::query()->whereKey($pid)->value('category');
            if ($cat && ! in_array($cat, ['finished', 'semi'], true)) {
                $this->merge(['_bad_category' => true]);
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if ($this->boolean('_bad_category')) {
                $v->errors()->add('product_material_id', '原料不能建 BOM，只有成品或半成品可以。');
            }
        });
    }
}
