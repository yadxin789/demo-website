<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\Quality;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Models\Defect;
use App\Modules\Mes\Models\WorkOrder;
use App\Modules\Mes\Models\WorkOrderOperation;
use App\Modules\Mes\Services\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 不良记录（M4-05 / M4-06）。
 *
 * 不良登记三件事：不良代码、数量、处置方式（返工 / 报废）。
 * 「转返工」不是新表，而是按原工单的产品与数量建一张返工工单，
 * 这样排产、领料、报工、检验全都复用同一条链路。
 */
class DefectController extends Controller
{
    /** 演示用不良代码，落在备注里给计划员参考 */
    public const CODES = [
        'WELD-COLD' => '虚焊 / 冷焊',
        'SOLDER-BRIDGE' => '连锡短路',
        'COMP-SHIFT' => '元件偏移',
        'SCRATCH' => '外观划伤',
        'FUNC-FAIL' => '功能不通',
    ];

    public function __construct(private readonly WorkOrderService $orders)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $disposal = (string) $request->query('disposal', '');
        $q = trim((string) $request->query('q', ''));

        $defects = Defect::query()
            ->with(['workOrder.product:id,code,name', 'operation:id,op_no,op_name', 'owner:id,name'])
            ->when($status !== '', fn ($b) => $b->where('status', $status))
            ->when($disposal !== '', fn ($b) => $b->where('disposal', $disposal))
            ->when($q !== '', function ($b) use ($q) {
                $b->where(function ($inner) use ($q) {
                    $inner->where('defect_code', 'like', "%{$q}%")
                        ->orWhereHas('workOrder', fn ($w) => $w->where('order_no', 'like', "%{$q}%"));
                });
            })
            ->orderByRaw("case status when 'open' then 0 when 'handling' then 1 else 2 end")
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $stat = [
            'open' => Defect::query()->where('status', 'open')->count(),
            'rework' => Defect::query()->where('disposal', 'rework')->count(),
            'scrap' => Defect::query()->where('disposal', 'scrap')->count(),
            'qty' => (float) Defect::query()->where('status', '!=', 'closed')->sum('qty'),
        ];

        return view('mes::quality.defects.index', [
            'defects' => $defects,
            'status' => $status,
            'disposal' => $disposal,
            'q' => $q,
            'stat' => $stat,
            'codes' => self::CODES,
            'orders' => self::orderOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_order_id' => ['required', 'integer', 'exists:mes.work_orders,id'],
            'operation_id' => ['nullable', 'integer', 'exists:mes.work_order_operations,id'],
            'defect_code' => ['required', 'string', 'max:32'],
            'qty' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'disposal' => ['required', 'in:rework,scrap'],
            'cause' => ['nullable', 'string', 'max:120'],
            'owner_id' => ['nullable', 'integer', 'exists:mes.users,id'],
        ], [], [
            'work_order_id' => '工单',
            'defect_code' => '不良代码',
            'qty' => '不良数量',
            'disposal' => '处置方式',
        ]);

        $data['status'] = 'open';

        $defect = Defect::query()->create($data);

        return back()->with('status', "不良已登记：{$defect->defect_code} × " . rtrim(rtrim(number_format((float) $defect->qty, 3), '0'), '.') . ' 件。');
    }

    public function update(Request $request, Defect $defect): RedirectResponse
    {
        $data = $request->validate([
            'disposal' => ['required', 'in:rework,scrap'],
            'status' => ['required', 'in:open,handling,closed'],
            'cause' => ['nullable', 'string', 'max:120'],
        ]);

        $defect->update($data);

        return back()->with('status', "不良 {$defect->defect_code} 已更新。");
    }

    /** 转返工：按原工单产品与不良数量建一张返工工单 */
    public function rework(Defect $defect): RedirectResponse
    {
        if ($defect->status === 'closed') {
            return back()->with('error', '这条不良已经结案了，不能再转返工。');
        }

        $order = $defect->workOrder;
        if (! $order) {
            return back()->with('error', '这条不良没有关联工单，转不了返工。');
        }

        $qty = (float) $defect->qty;
        if ($qty <= 0) {
            return back()->with('error', '不良数量为 0，不需要返工。');
        }

        $rework = $this->orders->create([
            'product_material_id' => (int) $order->product_material_id,
            'plan_qty' => $qty,
            'plan_start' => now()->toDateString(),
            'plan_end' => now()->addDays(3)->toDateString(),
            'priority' => 1,
            'customer' => $order->customer,
            'remark' => "返工单：源自 {$order->order_no} 的不良 {$defect->defect_code} × " . rtrim(rtrim(number_format($qty, 3), '0'), '.'),
        ]);

        $defect->update(['disposal' => 'rework', 'status' => 'closed']);

        return redirect()->route('mes.work-orders.show', $rework)
            ->with('status', "已生成返工工单 {$rework->order_no}（{$qty} 件），下达后即可派工。原不良记录已结案。");
    }

    /** 新建不良时选择工单 → 带出该工单的工序 */
    public function operations(Request $request)
    {
        $orderId = (int) $request->query('work_order_id');

        return response()->json(
            WorkOrderOperation::query()
                ->where('work_order_id', $orderId)
                ->orderBy('op_no')
                ->get(['id', 'op_no', 'op_name'])
                ->map(fn ($op) => ['id' => $op->id, 'label' => $op->op_no . ' ' . $op->op_name])
        );
    }

    /** 新建不良页面可选的工单（近 60 张，避免下拉太长） */
    public static function orderOptions()
    {
        return WorkOrder::query()
            ->with('product:id,code,name')
            ->orderByDesc('id')
            ->limit(60)
            ->get(['id', 'order_no', 'product_material_id']);
    }
}
