<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\Quality;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\Inspection;
use App\Modules\Mes\Models\InspectionTemplate;
use App\Modules\Mes\Services\InspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

/**
 * 检验模板（M4-01）。
 *
 * 模板 = 一组检验项（名称 / 标准 / 上下限 / 单位 / 合格样本值），
 * 检验单执行页按模板逐项录入实测值，判定规则在 InspectionService。
 */
class InspectionTemplateController extends Controller
{
    public function index(): View
    {
        $templates = InspectionTemplate::query()->orderBy('type')->orderBy('code')->get();

        // 每个模板被引用的检验单数，用来说明"改模板会影响哪些单"
        $usage = Inspection::query()
            ->selectRaw('template_id, count(*) as n')
            ->groupBy('template_id')
            ->pluck('n', 'template_id');

        return view('mes::quality.templates.index', [
            'templates' => $templates,
            'usage' => $usage,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['is_active'] = true;

        $t = InspectionTemplate::query()->create($data);

        return redirect()->route('mes.quality.templates.index')
            ->with('status', "检验模板 {$t->code} 已创建，共 " . count($data['items']) . ' 个检验项。');
    }

    public function update(Request $request, InspectionTemplate $template): RedirectResponse
    {
        $data = $this->validated($request);
        $template->update($data);

        return redirect()->route('mes.quality.templates.index')
            ->with('status', "检验模板 {$template->code} 已更新，共 " . count($data['items']) . ' 个检验项。');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32', 'regex:/^[A-Z]{2,6}-[A-Z0-9]{2,12}$/'],
            'name' => ['required', 'string', 'max:64'],
            'type' => ['required', 'in:IQC,IPQC,OQC'],
            'op_no' => ['nullable', 'integer', 'min:0', 'max:999'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.name' => ['nullable', 'string', 'max:48'],
            'items.*.std' => ['nullable', 'string', 'max:48'],
            'items.*.min' => ['nullable', 'numeric'],
            'items.*.max' => ['nullable', 'numeric'],
            'items.*.unit' => ['nullable', 'string', 'max:12'],
            'items.*.expect' => ['nullable', 'string', 'max:48'],
        ], [], [
            'code' => '模板编码',
            'name' => '模板名称',
            'type' => '检验类型',
        ]);

        // code 唯一性手工校验（update 要排除自己）
        $exists = InspectionTemplate::query()
            ->where('code', $request->input('code'))
            ->when($request->route('template'), fn ($q, $tpl) => $q->whereKeyNot($tpl instanceof InspectionTemplate ? $tpl->id : $tpl))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['code' => '这个模板编码已经被占用了。']);
        }

        $items = [];
        foreach ((array) $request->input('items', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $items[] = [
                'name' => $name,
                'std' => trim((string) ($row['std'] ?? '')),
                'min' => trim((string) ($row['min'] ?? '')),
                'max' => trim((string) ($row['max'] ?? '')),
                'unit' => trim((string) ($row['unit'] ?? '')),
                'expect' => trim((string) ($row['expect'] ?? '')),
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => '至少要有一项有名称的检验项。']);
        }

        return [
            'code' => strtoupper((string) $request->input('code')),            'name' => trim((string) $request->input('name')),
            'type' => (string) $request->input('type'),
            'op_no' => $request->filled('op_no') ? (int) $request->input('op_no') : null,
            'items' => $items,
        ];
    }
}
