<?php

namespace App\Modules\Shop\Http\Controllers\Exchange;

use App\Modules\Shop\Exceptions\ImportException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Services\Exchange\MasterDataExchange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

/**
 * 主数据 CSV 模板下载 / 导入 / 导出。
 *
 * 路由参数 type：products | customers | suppliers，统一挂 master.view 权限。
 * 导出采用流式响应，不在内存里拼整份文件；导出前先写 ACTION_EXPORT 审计。
 */
class ExchangeController extends Controller
{
    public function __construct(private readonly MasterDataExchange $exchange)
    {
    }

    public function show(string $type): View
    {
        $this->ensureType($type);

        return view('shop::exchange.import', [
            'type' => $type,
            'meta' => $this->exchange->meta($type),
            'importErrors' => session('import_errors', []),
            'imported' => session('imported_count'),
        ]);
    }

    public function template(string $type): StreamedResponse
    {
        $this->ensureType($type);

        return $this->csv($this->exchange->templateName($type), function ($handle) use ($type) {
            $this->exchange->write($handle, $type);
        });
    }

    public function export(string $type): StreamedResponse
    {
        $this->ensureType($type);

        $rows = $this->exchange->rows($type);
        $this->exchange->auditExport($type, count($rows));

        return $this->csv($this->exchange->exportName($type), function ($handle) use ($type, $rows) {
            $this->exchange->write($handle, $type, $rows);
        });
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->ensureType($type);

        $request->validate([
            'file' => ['required', 'file', 'max:5120'],
        ], [
            'file.required' => '请选择要导入的 CSV 文件',
            'file.file' => '上传的内容不是有效文件',
            'file.max' => '文件不能超过 5 MB，请拆分后再导入',
        ]);

        try {
            $result = $this->exchange->import($type, $request->file('file'));
        } catch (ImportException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        if ($result->failed()) {
            return back()->with('import_errors', $result->errors);
        }

        $meta = $this->exchange->meta($type);

        return redirect()->route($meta['index'])
            ->with('status', "已导入 {$result->imported} 条{$meta['label']}数据");
    }

    private function ensureType(string $type): void
    {
        abort_unless(MasterDataExchange::supports($type), 404);
    }

    private function csv(string $filename, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($writer) {
            $handle = fopen('php://output', 'w');
            $writer($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
