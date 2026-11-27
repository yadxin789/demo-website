<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\MasterData;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Http\Requests\Master\RoutingOperationRequest;
use App\Modules\Mes\Http\Requests\Master\RoutingRequest;
use App\Modules\Mes\Models\Material;
use App\Modules\Mes\Models\Routing;
use App\Modules\Mes\Models\RoutingOperation;
use App\Modules\Mes\Models\WorkCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 工艺路线（M1-03）：产品 → 工序序列。
 *
 * 工序号按 10 / 20 / 30 排（10 的倍数留出插工序的余地）。下达工单时按这里生成
 * 工单工序（work_order_operations），所以新增工序后不需要回填任何历史数据。
 */
class RoutingController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $routings = Routing::query()
            ->with(['product:id,code,name,category', 'operations:routing_id,op_no,std_minutes,inspection_required'])
            ->withCount('operations')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('code', 'like', "%{$q}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%"));
                });
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        return view('mes::master.routings.index', [
            'routings' => $routings,
            'q' => $q,
            'products' => Material::query()
                ->whereIn('category', ['finished', 'semi'])
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'category']),
            'stat' => [
                'count' => Routing::query()->count(),
                'ops' => RoutingOperation::query()->count(),
                'hours' => round((float) RoutingOperation::query()->sum('std_minutes') / 60, 1),
                'inspected' => RoutingOperation::query()->where('inspection_required', true)->count(),
            ],
        ]);
    }

    public function store(RoutingRequest $request): RedirectResponse
    {
        $routing = Routing::query()->create($request->validated());

        return redirect()
            ->route('mes.master.routings.show', $routing)
            ->with('status', "工艺路线 {$routing->code} 已创建，现在添加工序。");
    }

    public function show(Routing $routing): View
    {
        $routing->load(['product:id,code,name,unit,category', 'operations.workCenter:id,code,name,workshop,daily_capacity']);

        $total = (float) $routing->operations->sum('std_minutes');
        $byCenter = $routing->operations
            ->groupBy(fn (RoutingOperation $op) => $op->workCenter?->code ?? '未指定')
            ->map(fn ($group, $code) => [
                'code' => $code,
                'name' => $group->first()->workCenter?->name ?? '未指定工作中心',
                'minutes' => (float) $group->sum('std_minutes'),
                'ops' => $group->count(),
            ]);

        return view('mes::master.routings.show', [
            'routing' => $routing,
            'total' => $total,
            'byCenter' => $byCenter,
            'centers' => WorkCenter::query()->orderBy('code')->get(['id', 'code', 'name']),
        ]);
    }

    public function update(RoutingRequest $request, Routing $routing): RedirectResponse
    {
        $routing->update($request->validated());

        return redirect()->route('mes.master.routings.show', $routing)->with('status', '工艺路线头信息已更新。');
    }

    public function destroy(Routing $routing): RedirectResponse
    {
        $code = $routing->code;
        $routing->operations()->delete();
        $routing->delete();

        return redirect()->route('mes.master.routings.index')->with('status', "工艺路线 {$code} 已删除（含全部工序）。");
    }

    public function addOperation(RoutingOperationRequest $request, Routing $routing): RedirectResponse
    {
        $data = $request->validated();

        if ($routing->operations()->where('op_no', $data['op_no'])->exists()) {
            return back()->withErrors(['op_no' => "工序号 {$data['op_no']} 已经存在，工序号必须唯一。"] )->withInput();
        }

        $routing->operations()->create($data);

        return redirect()->route('mes.master.routings.show', $routing)->with('status', '工序已添加。');
    }

    public function updateOperation(RoutingOperationRequest $request, Routing $routing, RoutingOperation $operation): RedirectResponse
    {
        abort_unless($operation->routing_id === $routing->id, 404);

        $data = $request->validated();
        if ($routing->operations()->where('op_no', $data['op_no'])->whereKeyNot($operation->id)->exists()) {
            return back()->withErrors(['op_no' => "工序号 {$data['op_no']} 已经被别的工序占用。"] )->withInput();
        }

        $operation->update($data);

        return redirect()->route('mes.master.routings.show', $routing)->with('status', '工序已更新。');
    }

    public function destroyOperation(Routing $routing, RoutingOperation $operation): RedirectResponse
    {
        abort_unless($operation->routing_id === $routing->id, 404);
        $operation->delete();

        return redirect()->route('mes.master.routings.show', $routing)->with('status', '工序已删除。');
    }
}
