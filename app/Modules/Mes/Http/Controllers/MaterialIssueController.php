<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Models\MaterialIssue;
use App\Modules\Mes\Models\MaterialIssueLine;
use App\Modules\Mes\Models\WorkOrder;
use App\Modules\Mes\Services\IssueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * 物料领用（M3-04 ~ M3-06）。
 *
 * index  ：全部领料单，按状态筛；KPI 显示待发 / 部分 / 已发齐。
 * show   ：单张明细，逐行录入实发数量。
 * store  ：从工单详情一键生成领料单（按 BOM 展开）。
 * issue  ：提交发料，扣库存。
 */
class MaterialIssueController extends Controller
{
    public function __construct(private readonly IssueService $service)
    {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');
        $q = trim((string) $request->query('q', ''));

        $issues = MaterialIssue::query()
            ->with(['workOrder.product:id,code,name', 'lines.material:id,code,name,unit,std_cost', 'issuer:id,name'])
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when($q !== '', function ($builder) use ($q) {
                $builder->where(function ($inner) use ($q) {
                    $inner->where('issue_no', 'like', "%{$q}%")
                        ->orWhereHas('workOrder', fn ($w) => $w->where('order_no', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $stat = [
            'draft' => MaterialIssue::query()->where('status', 'draft')->count(),
            'partial' => MaterialIssue::query()->where('status', 'partial')->count(),
            'issued' => MaterialIssue::query()->where('status', 'issued')->count(),
            'lines' => MaterialIssueLine::query()->whereIn(
                'material_issue_id',
                MaterialIssue::query()->whereIn('status', ['draft', 'partial'])->select('id')
            )->count(),
        ];

        return view('mes::issues.index', [
            'issues' => $issues,
            'status' => $status,
            'q' => $q,
            'stat' => $stat,
        ]);
    }

    public function show(MaterialIssue $issue): View
    {
        $issue->load([
            'workOrder.product:id,code,name,unit',
            'issuer:id,name',
            'lines.material:id,code,name,spec,unit,stock_qty,std_cost',
        ]);

        return view('mes::issues.show', [
            'issue' => $issue,
            'summary' => $this->service->summary($issue),
        ]);
    }

    /** 工单详情按钮：按 BOM 生成领料单。 */
    public function store(WorkOrder $workOrder): RedirectResponse
    {
        try {
            $issue = $this->service->createFromOrder($workOrder);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $created = $issue->wasRecentlyCreated;
        $msg = $created
            ? "领料单 {$issue->issue_no} 已生成，共 {$issue->lines()->count()} 行等仓库发料。"
            : "这张工单已经有领料单 {$issue->issue_no}，直接打开核对即可。";

        return redirect()->route('mes.issues.show', $issue)->with('status', $msg);
    }

    public function issue(Request $request, MaterialIssue $issue): RedirectResponse
    {
        $qtys = [];
        foreach ((array) $request->input('qty', []) as $lineId => $value) {
            $qtys[(int) $lineId] = (float) $value;
        }

        if (array_sum($qtys) <= 0) {
            return back()->with('error', '至少给一行填实发数量再提交。');
        }

        try {
            $result = $this->service->issue($issue, $qtys, (int) auth('mes')->id());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = "发料完成：{$result['issued']} 行发齐、{$result['partial']} 行部分、{$result['pending']} 行待发。";
        if ($result['shortage'] !== []) {
            $msg .= ' 有 ' . count($result['shortage']) . ' 行库存不足未发：'
                . implode('、', array_map(fn ($s) => "{$s['code']}（要 {$s['want']}，库存 {$s['stock']}）", $result['shortage']));
        }

        return redirect()
            ->route('mes.issues.show', $issue)
            ->with($result['shortage'] === [] ? 'status' : 'error', $msg);
    }
}
