<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\PayrollItem;
use App\Modules\Hr\Models\PayrollRun;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\SalaryStructure;
use App\Modules\Hr\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 薪资（H3-01 ~ H3-06）：结构、批次、明细、我的工资条。
 *
 * 金额一律在服务层算，视图只渲染；工资条只按本人工号取数（HC-M08）。
 */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollService $payroll)
    {
    }

    // ---------- 薪资结构 ----------
    public function structures(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $deptId = (int) $request->query('dept', 0);

        $positions = Position::query()
            ->with(['department:id,code,name'])
            ->when($q !== '', fn ($query) => $query->where(function ($inner) use ($q) {
                $inner->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
            }))
            ->when($deptId > 0, fn ($query) => $query->where('department_id', $deptId))
            ->orderBy('code')
            ->get();

        $structures = SalaryStructure::query()->orderBy('effective_date')->get()->keyBy('position_id');

        $totals = [
            'positions' => $positions->count(),
            'covered' => $structures->count(),
            'monthly' => round((float) $structures->sum(fn ($s) => (float) $s->base + (float) $s->post + (float) $s->performance + (float) $s->allowance), 2),
        ];

        return view('hr::payroll.structures', [
            'positions' => $positions,
            'structures' => $structures,
            'q' => $q,
            'deptId' => $deptId,
            'totals' => $totals,
            'departments' => Department::query()->orderBy('level')->orderBy('sort')->get(['id', 'code', 'name']),
        ]);
    }

    public function structureStore(Request $request): RedirectResponse
    {
        $data = $this->validateStructure($request);

        SalaryStructure::query()->create($data + ['effective_date' => $data['effective_date'] ?? now()->toDateString()]);

        return redirect()->route('hr.payroll.structures')
            ->with('status', '薪资档已新增，下个月生成工资单时按生效日取最新一条。');
    }

    public function structureUpdate(Request $request, SalaryStructure $structure): RedirectResponse
    {
        $data = $this->validateStructure($request);

        $structure->update($data + ['effective_date' => $data['effective_date'] ?? $structure->effective_date]);

        return redirect()->route('hr.payroll.structures')
            ->with('status', '薪资档已更新。');
    }

    // ---------- 工资批次 ----------
    public function runs(Request $request): View
    {
        $rows = PayrollRun::query()->orderByDesc('period')->get();

        $itemsCount = PayrollItem::query()
            ->selectRaw('run_id, count(*) as n')
            ->groupBy('run_id')
            ->pluck('n', 'run_id');

        return view('hr::payroll.runs', [
            'rows' => $rows,
            'itemsCount' => $itemsCount,
            'period' => $this->payroll->periodNow(),
            'statusText' => self::runStatusText(),
            'structureCount' => SalaryStructure::query()->count(),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ], [], ['period' => '月份']);

        try {
            $run = $this->payroll->generate($data['period']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('hr.payroll.runs.show', $run->id)
            ->with('status', "{$run->period} 工资单已生成：{$run->employee_count} 人，实发合计 ¥" . number_format((float) $run->total_amount, 2) . '。');
    }

    public function runShow(PayrollRun $run): View
    {
        $rows = PayrollItem::query()
            ->with(['employee:id,employee_no,name,department_id', 'employee.department:id,name'])
            ->where('run_id', $run->id)
            ->orderBy('employee_no')
            ->paginate(20);

        return view('hr::payroll.run-show', [
            'run' => $run,
            'rows' => $rows,
            'summary' => $this->payroll->summary($run),
            'statusText' => self::runStatusText(),
        ]);
    }

    public function runExport(PayrollRun $run): StreamedResponse
    {
        $filename = "payroll-{$run->period}.csv";

        return response()->streamDownload(function () use ($run) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['工号', '姓名', '基本', '岗位', '绩效', '补贴', '加班费', '应发', '请假扣款', '社保', '个税', '实发']);

            PayrollItem::query()
                ->where('run_id', $run->id)
                ->orderBy('employee_no')
                ->chunk(200, function ($items) use ($out) {
                    foreach ($items as $i) {
                        fputcsv($out, [
                            $i->employee_no,
                            $i->employee_name,
                            $i->base,
                            $i->post,
                            $i->performance,
                            $i->allowance,
                            $i->overtime_pay,
                            $i->gross,
                            $i->leave_deduct,
                            $i->social_insurance,
                            $i->tax,
                            $i->net_pay,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function runConfirm(PayrollRun $run): RedirectResponse
    {
        try {
            $this->payroll->confirm($run);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('hr.payroll.runs.show', $run->id)->with('status', '工资单已确认，可以标记发放了。');
    }

    public function runPay(PayrollRun $run): RedirectResponse
    {
        try {
            $this->payroll->pay($run);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('hr.payroll.runs.show', $run->id)->with('status', '已标记发放，实发金额按批次记录归档。');
    }

    // ---------- 我的工资条 ----------
    public function my(): View
    {
        $user = auth('hr')->user();
        $employeeNo = (string) ($user?->employee_no ?? '');

        $items = $employeeNo !== '' ? $this->payroll->myItems($employeeNo, 6) : collect();

        $latest = $items->first();

        return view('hr::payroll.my', [
            'employeeNo' => $employeeNo,
            'items' => $items,
            'latest' => $latest,
            'runPeriods' => PayrollRun::query()->orderByDesc('period')->pluck('period', 'id'),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateStructure(Request $request): array
    {
        return $request->validate([
            'position_id' => ['required', 'integer', 'exists:hr.positions,id'],
            'base' => ['required', 'numeric', 'min:0', 'max:200000'],
            'post' => ['required', 'numeric', 'min:0', 'max:200000'],
            'performance' => ['required', 'numeric', 'min:0', 'max:200000'],
            'allowance' => ['required', 'numeric', 'min:0', 'max:200000'],
            'effective_date' => ['nullable', 'date'],
        ], [], [
            'position_id' => '岗位',
            'base' => '基本工资',
            'post' => '岗位工资',
            'performance' => '绩效工资',
            'allowance' => '补贴',
        ]);
    }

    /** @return array<string, string> */
    public static function runStatusText(): array
    {
        return [
            'draft' => '草稿',
            'confirmed' => '已确认',
            'paid' => '已发放',
        ];
    }
}
