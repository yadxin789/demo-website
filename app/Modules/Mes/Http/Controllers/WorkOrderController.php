<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Http\Requests\WorkOrderRequest;
use App\Modules\Mes\Models\WorkOrder;
use App\Modules\Mes\Models\WorkOrderOperation;
use App\Modules\Mes\Services\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 工单（M2）：列表 / 详情 / 建单 / 下达 / 派工。
 *
 * 详情页要做三件事：让客户看清这张单做到第几道工序、卡在哪、料够不够。
 */
class WorkOrderController extends Controller
{
    private const OPEN = ['released', 'in_progress'];

    public function __construct(private readonly WorkOrderService $service)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $orders = WorkOrder::query()
            ->with('product:id,code,name,unit')
            ->withCount([
                'operations as ops_total',
                'operations as ops_done' => fn ($query) => $query->where('status', 'done'),
            ])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('order_no', 'like', "%{$q}%")
                        ->orWhere('customer', 'like', "%{$q}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%"));
                });
            })
            ->when($status !== '' && $status !== 'open', fn ($query) => $query->where('status', $status))
            ->when($status === 'open', fn ($query) => $query->whereIn('status', self::OPEN))
            ->orderByRaw("case status when 'in_progress' then 0 when 'released' then 1 when 'draft' then 2 when 'completed' then 3 else 4 end")
            ->orderBy('plan_end')
            ->paginate(15)
            ->withQueryString();

        $overdue = WorkOrder::query()
            ->whereNotIn('status', ['completed', 'closed'])
            ->whereNotNull('plan_end')
            ->whereDate('plan_end', '<', now()->toDateString())
            ->count();

        return view('mes::work-orders.index', [
            'orders' => $orders,
            'q' => $q,
            'status' => $status,
            'products' => $this->service->products(),
            'stat' => [
                'open' => WorkOrder::query()->whereIn('status', self::OPEN)->count(),
                'draft' => WorkOrder::query()->where('status', 'draft')->count(),
                'completed' => WorkOrder::query()->where('status', 'completed')->count(),
                'overdue' => $overdue,
                'planned' => (float) WorkOrder::query()->whereIn('status', self::OPEN)->sum('plan_qty'),
            ],
        ]);
    }

    public function show(WorkOrder $workOrder): View
    {
        $workOrder->load([
            'product:id,code,name,unit,category,spec',
            'bom.lines.material:id,code,name,unit,std_cost,stock_qty',
            'routing:id,code,version',
            'operations.workCenter:id,code,name',
            'operations.assignee:id,name',
            'reports' => fn ($q) => $q->latest('reported_at')->limit(10),
            'reports.user:id,name',
            'reports.shift:id,code,name',
            'issues.lines.material:id,code,name,unit',
            'inspections' => fn ($q) => $q->latest('id')->limit(5),
            'defects' => fn ($q) => $q->latest('id')->limit(5),
        ]);

        // 齐套检查：按 BOM × 计划数量算需求，和现有库存比
        $shortages = [];
        if ($workOrder->bom) {
            foreach ($workOrder->bom->lines as $line) {
                $need = (float) $line->qty_per * (1 + (float) $line->loss_rate) * (float) $workOrder->plan_qty;
                $stock = (float) ($line->material?->stock_qty ?? 0);
                if ($stock < $need) {
                    $shortages[] = [
                        'code' => $line->material?->code,
                        'name' => $line->material?->name,
                        'unit' => $line->material?->unit,
                        'need' => round($need, 3),
                        'stock' => $stock,
                        'gap' => round($need - $stock, 3),
                    ];
                }
            }
        }

        $reportedOk = (float) $workOrder->reports->sum('ok_qty');
        $reportedNg = (float) $workOrder->reports->sum('defect_qty');
        $reportedMinutes = (int) $workOrder->reports->sum('minutes');

        return view('mes::work-orders.show', [
            'order' => $workOrder,
            'shortages' => $shortages,
            'reportedOk' => $reportedOk,
            'reportedNg' => $reportedNg,
            'reportedMinutes' => $reportedMinutes,
            'leader' => $this->service->defaultLeader(),
            'assignees' => \App\Modules\Mes\Models\User::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(WorkOrderRequest $request): RedirectResponse
    {
        $order = $this->service->create($request->validated());

        $hint = $order->routing
            ? '已按产品自动挂上 BOM 与工艺路线，下达后即可派工。'
            : '这个产品还没有工艺路线，下达后不会生成工序，建议先去主数据补一条。';

        return redirect()
            ->route('mes.work-orders.show', $order)
            ->with('status', "工单 {$order->order_no} 已创建（草稿）。{$hint}");
    }

    public function update(WorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        if (in_array($workOrder->status, ['completed', 'closed'], true)) {
            return back()->with('error', '已完工的工单不再改数量，需要返工请新建一张返工单。');
        }

        $data = $request->validated();
        $oldQty = (float) $workOrder->plan_qty;
        $newQty = (float) $data['plan_qty'];

        $workOrder->fill($data);

        // 数量变了，未开工的工序计划量跟着走
        if ($oldQty !== $newQty) {
            $workOrder->operations()->where('status', 'pending')->update(['plan_qty' => $newQty]);
        }

        $workOrder->save();

        return redirect()->route('mes.work-orders.show', $workOrder)->with('status', '工单信息已更新。');
    }

    public function release(WorkOrder $workOrder): RedirectResponse
    {
        $result = $this->service->release($workOrder);

        return redirect()
            ->route('mes.work-orders.show', $workOrder)
            ->with($result['ok'] ? 'status' : 'error', $result['message']);
    }

    public function dispatch(Request $request, WorkOrder $workOrder, WorkOrderOperation $operation): RedirectResponse
    {
        abort_unless($operation->work_order_id === $workOrder->id, 404);

        $request->validate([
            'assignee_id' => ['nullable', 'integer', 'exists:mes.users,id'],
            'team' => ['nullable', 'string', 'max:64'],
        ], [], ['assignee_id' => '负责人', 'team' => '班组']);

        $this->service->dispatch(
            $operation,
            $request->filled('assignee_id') ? (int) $request->input('assignee_id') : null,
            $request->input('team')
        );

        return redirect()
            ->route('mes.work-orders.show', $workOrder)
            ->with('status', "工序 {$operation->op_no} {$operation->op_name} 已派工。");
    }

    public function dispatchAll(WorkOrder $workOrder): RedirectResponse
    {
        if (! in_array($workOrder->status, self::OPEN, true)) {
            return back()->with('error', '先下达工单，才能派工。');
        }

        $leader = $this->service->defaultLeader();
        $count = $this->service->dispatchAllPending($workOrder, $leader?->id, '一号车间');

        return redirect()
            ->route('mes.work-orders.show', $workOrder)
            ->with($count > 0 ? 'status' : 'error',
                $count > 0
                    ? "已把 {$count} 道待派工序派给" . ($leader?->name ?? '车间') . '。'
                    : '没有待派的工序（可能都已经派过了）。');
    }
}
