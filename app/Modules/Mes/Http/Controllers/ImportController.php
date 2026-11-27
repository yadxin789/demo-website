<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Services\Import\ImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel 导入（M1-11~M1-14）。
 *
 * 流程：上传 → 解析校验 → 预览（哪行有问题、为什么）→ 确认入库。
 * 预览数据放 session，不落文件，提交完即清。
 */
class ImportController extends Controller
{
    private const MAX_KB = 5120;   // 5MB

    public function __construct(private readonly ImportService $import)
    {
    }

    public function index(): View
    {
        $results = [];
        foreach (array_keys(ImportService::TYPES) as $type) {
            $results[$type] = session("mes_import_result_{$type}");
        }

        $pending = [];
        foreach (array_keys(ImportService::TYPES) as $type) {
            $parsed = session("mes_import_parsed_{$type}");
            if (is_array($parsed)) {
                $pending[$type] = ['total' => $parsed['total'] ?? 0, 'bad' => $parsed['bad'] ?? 0];
            }
        }

        return view('mes::import.index', [
            'types' => ImportService::TYPES,
            'results' => $results,
            'pending' => $pending,
        ]);
    }

    public function template(string $type): StreamedResponse
    {
        $def = $this->definition($type);

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('导入模板');
        $sheet->fromArray($def['columns'], null, 'A1');
        $sheet->fromArray($def['sample'], null, 'A2');

        $lastCol = chr(64 + count($def['columns']));
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFEFF3F8');

        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setWidth(18);
        }
        $sheet->getStyle("A1:{$lastCol}" . (count($def['sample']) + 1))->getAlignment()->setVertical('center');

        $name = 'MES-' . $def['label'] . '-导入模板.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function upload(Request $request, string $type): RedirectResponse
    {
        $this->definition($type);

        $request->validate([
            'file' => ['required', 'file', 'max:' . self::MAX_KB],
        ], [], ['file' => '文件']);

        $file = $request->file('file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'csv'], true)) {
            return back()->withErrors(['file' => '只支持 .xlsx 与 .csv。xls（旧格式）请先另存为 xlsx。']);
        }

        try {
            $parsed = $this->import->parse($type, (string) $file->getRealPath(), $ext);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['file' => '文件读不出来，可能是加密文件或格式被改过。']);
        }

        $parsed['file'] = $file->getClientOriginalName();
        $parsed['at'] = now()->format('Y-m-d H:i');
        session(["mes_import_parsed_{$type}" => $parsed]);
        session()->forget("mes_import_result_{$type}");

        return redirect()->route('mes.import.preview', $type);
    }

    public function preview(string $type): View|RedirectResponse
    {
        $def = $this->definition($type);
        $parsed = session("mes_import_parsed_{$type}");

        if (! is_array($parsed)) {
            return redirect()->route('mes.import.index')->with('status', '还没有上传文件，先选一个表格。');
        }

        return view('mes::import.preview', [
            'type' => $type,
            'def' => $def,
            'parsed' => $parsed,
        ]);
    }

    public function commit(string $type): RedirectResponse
    {
        $this->definition($type);
        $parsed = session("mes_import_parsed_{$type}");

        if (! is_array($parsed)) {
            return redirect()->route('mes.import.index')->with('error', '预览数据已过期，请重新上传。');
        }

        $result = $this->import->commit($type, $parsed['rows'] ?? []);
        $result['file'] = $parsed['file'] ?? '';
        $result['at'] = now()->format('Y-m-d H:i');
        $result['skipped'] = $parsed['bad'] ?? 0;

        session(["mes_import_result_{$type}" => $result]);
        session()->forget("mes_import_parsed_{$type}");

        $summary = "入库完成：新增 {$result['created']} 条";
        if ($result['updated'] > 0) {
            $summary .= "，更新 {$result['updated']} 条";
        }
        if ($result['skipped'] > 0) {
            $summary .= "，跳过 {$result['skipped']} 行问题数据";
        }
        if ($result['failed'] > 0) {
            $summary .= "，失败 {$result['failed']} 条";
        }

        return redirect()->route('mes.import.index')->with('status', $summary);
    }

    public function discard(string $type): RedirectResponse
    {
        $this->definition($type);
        session()->forget("mes_import_parsed_{$type}");

        return redirect()->route('mes.import.index')->with('status', '已放弃这次导入，什么都没写进库。');
    }

    /** @return array{label:string, columns:array, sample:array} */
    private function definition(string $type): array
    {
        if (! isset(ImportService::TYPES[$type])) {
            abort(404);
        }

        return ImportService::TYPES[$type];
    }
}
