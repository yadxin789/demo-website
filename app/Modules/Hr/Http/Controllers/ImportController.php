<?php

declare(strict_types=1);

namespace App\Modules\Hr\Http\Controllers;

use App\Modules\Hr\Models\AttendanceRecord;
use App\Modules\Hr\Models\Department;
use App\Modules\Hr\Models\Employee;
use App\Modules\Hr\Models\Position;
use App\Modules\Hr\Models\Shift;
use App\Modules\Hr\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 导入导出（H5-06 ~ H5-08）。
 *
 * 两阶段：上传 → 解析预览（标红错误行）→ 确认入库。
 * 用 CSV 而不是 xlsx：服务器不用装 zip/xml 扩展，客户用 Excel 也能另存为 CSV。
 */
class ImportController extends Controller
{
    private const SESSION_KEY = 'hr.import.preview';

    private const EMPLOYEE_HEADER = ['工号', '姓名', '性别', '手机', '部门编码', '岗位编码', '入职日期', '学历'];

    private const ATTENDANCE_HEADER = ['工号', '日期', '上班打卡', '下班打卡'];

    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    public function index(): View
    {
        return view('hr::import.index', [
            'stats' => [
                'employees' => Employee::query()->count(),
                'records' => AttendanceRecord::query()->count(),
                'runs' => DB::connection('hr')->table('payroll_runs')->count(),
            ],
            'preview' => session(self::SESSION_KEY),
        ]);
    }

    /** 模板下载：type = employee|attendance */
    public function template(string $type): StreamedResponse
    {
        $header = $type === 'attendance' ? self::ATTENDANCE_HEADER : self::EMPLOYEE_HEADER;
        $sample = $type === 'attendance'
            ? ['HR2026001', now()->toDateString(), '08:55', '18:05']
            : ['HR2026099', '示例姓名', '女', '13800000000', 'D001', 'P001', now()->toDateString(), '本科'];

        return response()->streamDownload(function () use ($header, $sample) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            fputcsv($out, $sample);
            fclose($out);
        }, "hr-{$type}-template.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function upload(Request $request, string $type): RedirectResponse
    {
        if (! in_array($type, ['employee', 'attendance'], true)) {
            return back()->with('error', '不认识的导入类型。');
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [], ['file' => '文件']);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $rows = $this->parseCsv($file);

        if ($rows === []) {
            return back()->with('error', '文件里没有读到数据行（第一行必须是表头）。');
        }

        $preview = $type === 'attendance'
            ? $this->validateAttendance($rows)
            : $this->validateEmployees($rows);

        session([self::SESSION_KEY => ['type' => $type, 'rows' => $preview['rows'], 'total' => count($rows)]]);

        return redirect()->route('hr.import.index');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $preview = session(self::SESSION_KEY);
        if (! is_array($preview) || ($preview['rows'] ?? []) === []) {
            return redirect()->route('hr.import.index')->with('error', '没有待入库的预览数据，先上传文件。');
        }

        $type = $preview['type'];
        $ok = array_values(array_filter($preview['rows'], fn ($r) => $r['ok'] === true));

        if ($ok === []) {
            return redirect()->route('hr.import.index')->with('error', '全部行都有错误，一条也没入库。先修文件再传。');
        }

        $inserted = 0;
        $updated = 0;

        DB::connection('hr')->transaction(function () use ($type, $ok, &$inserted, &$updated) {
            if ($type === 'employee') {
                foreach ($ok as $row) {
                    $exists = Employee::query()->where('employee_no', $row['data']['employee_no'])->first();
                    if ($exists === null) {
                        Employee::query()->create($row['data'] + ['status' => 'active']);
                        $inserted++;
                    } else {
                        $exists->update($row['data']);
                        $updated++;
                    }
                }
            } else {
                $shift = Shift::query()->where('code', 'S01')->first();
                foreach ($ok as $row) {
                    $judged = $this->attendance->judge(
                        $row['data']['clock_in'],
                        $row['data']['clock_out'],
                        $shift,
                        $row['data']['work_date']
                    );

                    AttendanceRecord::query()->updateOrCreate(
                        ['employee_id' => $row['data']['employee_id'], 'work_date' => $row['data']['work_date']],
                        [
                            'clock_in' => $row['data']['clock_in'],
                            'clock_out' => $row['data']['clock_out'],
                            'shift_id' => $shift?->id,
                            'status' => $judged['status'],
                            'late_minutes' => $judged['late_minutes'],
                            'early_minutes' => $judged['early_minutes'],
                            'remark' => '导入',
                        ]
                    );
                    $inserted++;
                }
            }
        });

        session()->forget(self::SESSION_KEY);

        $text = $type === 'employee'
            ? "花名册入库完成：新增 {$inserted} 人，更新 {$updated} 人。"
            : "打卡入库完成：{$inserted} 条记录（已按 S01 标准班重算状态）。";

        return redirect()->route('hr.import.index')->with('status', $text);
    }

    public function cancel(): RedirectResponse
    {
        session()->forget(self::SESSION_KEY);

        return redirect()->route('hr.import.index')->with('status', '已放弃本次导入。');
    }

    /** 考勤月报导出 */
    public function exportAttendance(Request $request): StreamedResponse
    {
        $month = (string) $request->query('month', now()->format('Y-m'));
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));

        $rows = DB::connection('hr')->table('attendance_records as r')
            ->join('employees as e', 'e.id', '=', 'r.employee_id')
            ->leftJoin('departments as d', 'd.id', '=', 'e.department_id')
            ->whereBetween('r.work_date', [$start, $end])
            ->orderBy('r.work_date')
            ->orderBy('e.employee_no')
            ->get(['e.employee_no', 'e.name', 'd.name as dept', 'r.work_date', 'r.clock_in', 'r.clock_out', 'r.status', 'r.late_minutes', 'r.early_minutes']);

        $statusText = ['normal' => '正常', 'late' => '迟到', 'early' => '早退', 'absent' => '缺勤', 'missing' => '缺卡'];

        return response()->streamDownload(function () use ($rows, $statusText) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['工号', '姓名', '部门', '日期', '上班', '下班', '状态', '迟到(分)', '早退(分)']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->employee_no,
                    $r->name,
                    $r->dept ?? '—',
                    $r->work_date,
                    $r->clock_in,
                    $r->clock_out,
                    $statusText[$r->status] ?? $r->status,
                    $r->late_minutes,
                    $r->early_minutes,
                ]);
            }

            fclose($out);
        }, "attendance-{$month}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * 解析 CSV（UTF-8 with/without BOM 都吃）。
     *
     * @return array<int, array<int, string>>
     */
    private function parseCsv(UploadedFile $file): array
    {
        $raw = (string) file_get_contents($file->getRealPath());
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
        $out = [];
        foreach ($lines as $i => $line) {
            if ($line === '') {
                continue;
            }
            if ($i === 0) {
                continue;   // 表头
            }
            $out[] = array_map('trim', str_getcsv($line));
        }

        return $out;
    }

    /**
     * 花名册校验。
     *
     * @param  array<int, array<int, string>>  $rows
     * @return array{rows: array<int, array<string, mixed>>}
     */
    private function validateEmployees(array $rows): array
    {
        $deptCodes = Department::query()->pluck('id', 'code');
        $posCodes = Position::query()->pluck('id', 'code');

        $out = [];
        $seen = [];

        foreach ($rows as $i => $cols) {
            $errors = [];
            $data = [
                'employee_no' => $cols[0] ?? '',
                'name' => $cols[1] ?? '',
                'gender' => in_array($cols[2] ?? '', ['男', '女'], true) ? $cols[2] : null,
                'phone' => $cols[3] ?? null,
                'department_id' => $deptCodes[$cols[4] ?? ''] ?? null,
                'position_id' => $posCodes[$cols[5] ?? ''] ?? null,
                'hire_date' => null,
                'education' => $cols[7] ?? null,
            ];

            if ($data['employee_no'] === '') {
                $errors[] = '工号为空';
            } elseif (isset($seen[$data['employee_no']])) {
                $errors[] = '工号在本文件中重复';
            }
            $seen[$data['employee_no']] = true;

            if ($data['name'] === '') {
                $errors[] = '姓名为空';
            }

            if (($cols[4] ?? '') !== '' && $data['department_id'] === null) {
                $errors[] = "部门编码 {$cols[4]} 不存在";
            }
            if (($cols[5] ?? '') !== '' && $data['position_id'] === null) {
                $errors[] = "岗位编码 {$cols[5]} 不存在";
            }

            $hireDate = $cols[6] ?? '';
            if ($hireDate !== '') {
                try {
                    $data['hire_date'] = Carbon::parse($hireDate)->toDateString();
                } catch (\Throwable) {
                    $errors[] = "入职日期「{$hireDate}」格式不对";
                }
            }

            $out[] = [
                'line' => $i + 2,
                'cells' => $cols,
                'data' => $data,
                'ok' => $errors === [],
                'errors' => $errors,
                'note' => $data['employee_no'] !== '' && Employee::query()->where('employee_no', $data['employee_no'])->exists()
                    ? '已存在，将覆盖更新'
                    : '新增',
            ];
        }

        return ['rows' => $out];
    }

    /**
     * 打卡校验。
     *
     * @param  array<int, array<int, string>>  $rows
     * @return array{rows: array<int, array<string, mixed>>}
     */
    private function validateAttendance(array $rows): array
    {
        $employeeNos = Employee::query()->pluck('id', 'employee_no');

        $out = [];
        $shift = Shift::query()->where('code', 'S01')->first();

        foreach ($rows as $i => $cols) {
            $errors = [];
            $no = $cols[0] ?? '';
            $date = $cols[1] ?? '';
            $in = $cols[2] ?? '';
            $outTime = $cols[3] ?? '';

            $data = [
                'employee_id' => $employeeNos[$no] ?? null,
                'employee_no' => $no,
                'work_date' => null,
                'clock_in' => $in !== '' ? $in : null,
                'clock_out' => $outTime !== '' ? $outTime : null,
            ];

            if ($no === '') {
                $errors[] = '工号为空';
            } elseif ($data['employee_id'] === null) {
                $errors[] = "工号 {$no} 在员工档案里不存在";
            }

            try {
                $data['work_date'] = Carbon::parse($date)->toDateString();
            } catch (\Throwable) {
                $errors[] = "日期「{$date}」格式不对";
            }

            foreach ([['上班打卡', $in], ['下班打卡', $outTime]] as [$label, $time]) {
                if ($time !== '' && ! preg_match('/^\d{1,2}:\d{2}$/', $time)) {
                    $errors[] = "{$label}「{$time}」应为 HH:MM";
                }
            }

            $judged = $errors === [] && $data['work_date'] !== null
                ? $this->attendance->judge($data['clock_in'], $data['clock_out'], $shift, $data['work_date'])
                : null;

            $statusText = ['normal' => '正常', 'late' => '迟到', 'early' => '早退', 'missing' => '缺卡', 'absent' => '缺勤'];

            $out[] = [
                'line' => $i + 2,
                'cells' => $cols,
                'data' => $data,
                'ok' => $errors === [],
                'errors' => $errors,
                'note' => $judged !== null ? ($statusText[$judged['status']] ?? $judged['status']) : '—',
            ];
        }

        return ['rows' => $out];
    }
}
