<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\Quality;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\Defect;
use App\Modules\Mes\Models\Inspection;
use App\Modules\Mes\Services\InspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 检验单（M4-03 / M4-04）。
 *
 * index  ：IQC / IPQC / OQC 分类 + 结果筛选，KPI 显示待检、今日已检、不合格。
 * show   ：按模板逐项录入实测值；已判定的单可以重检（会覆盖结果）。
 * submit ：跑 InspectionService::submit，写明细并定 overall。
 */
class InspectionController extends Controller
{
    public function __construct(private readonly InspectionService $service)
    {
    }

    public function index(Request $request): View
    {
        $type = (string) $request->query('type', '');
        $result = (string) $request->query('result', '');
        $q = trim((string) $request->query('q', ''));

        $inspections = Inspection::query()
            ->with(['workOrder.product:id,code,name', 'template:id,code,name,type', 'inspector:id,name', 'results'])
            ->when($type !== '', fn ($b) => $b->where('type', $type))
            ->when($result === 'pending', fn ($b) => $b->whereNull('result'))
            ->when(in_array($result, ['pass', 'fail'], true), fn ($b) => $b->where('result', $result))
            ->when($q !== '', function ($b) use ($q) {
                $b->where(function ($inner) use ($q) {
                    $inner->where('inspection_no', 'like', "%{$q}%")
                        ->orWhereHas('workOrder', fn ($w) => $w->where('order_no', 'like', "%{$q}%"));
                });
            })
            ->orderByRaw('result is null desc')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $base = Inspection::query();

        return view('mes::quality.inspections.index', [
            'inspections' => $inspections,
            'type' => $type,
            'result' => $result,
            'q' => $q,
            'stat' => [
                'pending' => (clone $base)->whereNull('result')->count(),
                'today' => (clone $base)->whereDate('inspected_at', now()->toDateString())->count(),
                'fail' => (clone $base)->where('result', 'fail')->count(),
                'pass' => (clone $base)->where('result', 'pass')->count(),
                'defects' => Defect::query()->where('status', 'open')->count(),
            ],
        ]);
    }

    public function show(Inspection $inspection): View
    {
        $inspection->load([
            'workOrder.product:id,code,name,unit',
            'operation:id,op_no,op_name',
            'template:id,code,name,type,items',
            'inspector:id,name',
            'results',
        ]);

        // 已判定过的单：把上次实测值填回表单，方便重检时改一两项
        $previous = [];
        foreach ($inspection->results as $r) {
            $previous[$r->item_name] = (string) $r->actual_value;
        }

        return view('mes::quality.inspections.show', [
            'inspection' => $inspection,
            'service' => $this->service,
            'previous' => $previous,
            'items' => (array) ($inspection->template?->items ?? []),
        ]);
    }

    public function submit(Request $request, Inspection $inspection): RedirectResponse
    {
        $actuals = [];
        foreach ((array) $request->input('actual', []) as $idx => $value) {
            $actuals[(int) $idx] = (string) $value;
        }

        $result = $this->service->submit($inspection, $actuals, (int) auth('mes')->id());

        $msg = $result['overall'] === 'pass'
            ? "检验单 {$inspection->inspection_no} 判定合格（{$result['ok']} 项全过）。"
            : "检验单 {$inspection->inspection_no} 判定不合格：{$result['ng']} 项超差，后续到不良记录页登记处置。";

        return redirect()
            ->route('mes.quality.inspections.show', $inspection)
            ->with($result['overall'] === 'pass' ? 'status' : 'error', $msg);
    }
}
