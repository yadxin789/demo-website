<?php

namespace App\Modules\Shop\Http\Controllers\Report;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Report\ProfitStockReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ProfitStockController extends Controller
{
    public function __construct(
        protected ProfitStockReportService $reports,
        protected AuditLogger $audit,
    ) {
    }

    public function index(Request $request): View
    {
        $productId = $request->filled('product_id') ? (int) $request->input('product_id') : null;
        $start = $this->parseDate($request->input('start'));
        $end = $this->parseDate($request->input('end'));

        $inventory = $this->reports->inventoryValuation($productId);
        $profit = $this->reports->profitMargin($start, $end, $productId);

        return view('shop::report.profit-stock.index', [
            'stockRows' => $inventory['rows'],
            'totalQty' => $inventory['totalQty'],
            'totalValuation' => $inventory['totalValuation'],
            'profitRows' => $profit['rows'],
            'revenue' => $profit['revenue'],
            'cost' => $profit['cost'],
            'grossProfit' => $profit['grossProfit'],
            'margin' => $profit['margin'],
            'products' => $this->reports->products(),
            'productId' => $productId,
            'start' => $start?->toDateString(),
            'end' => $end?->toDateString(),
        ]);
    }

    /**
     * 报表 CSV 导出（section=stock 存货估值 / profit 毛利分析，默认两者各导一份由前端选择）。
     * 导出写 ACTION_EXPORT 审计，中文走 UTF-8 + BOM，Excel 直接打开不乱码。
     */
    public function export(Request $request): StreamedResponse
    {
        $section = $request->input('section') === 'stock' ? 'stock' : 'profit';
        $productId = $request->filled('product_id') ? (int) $request->input('product_id') : null;
        $start = $this->parseDate($request->input('start'));
        $end = $this->parseDate($request->input('end'));

        if ($section === 'stock') {
            $inventory = $this->reports->inventoryValuation($productId);
            $headers = ['商品编码', '商品名称', '结存数量', '标准成本', '估值金额'];
            $rows = $inventory['rows']->map(fn (array $row) => [
                $row['code'], $row['name'], $row['qty'], $row['standard_cost'], $row['valuation'],
            ])->all();
            $rows[] = ['合计', '', $inventory['totalQty'], '', $inventory['totalValuation']];
        } else {
            $profit = $this->reports->profitMargin($start, $end, $productId);
            $headers = ['商品编码', '商品名称', '销量', '收入', '标准成本', '毛利', '毛利率(%)'];
            $rows = $profit['rows']->map(fn (array $row) => [
                $row['code'], $row['name'], $row['qty'], $row['revenue'], $row['cost'], $row['grossProfit'], $row['margin'],
            ])->all();
            $rows[] = ['合计', '', '', $profit['revenue'], $profit['cost'], $profit['grossProfit'], $profit['margin']];
        }

        $label = $section === 'stock' ? '存货估值' : '毛利分析';
        $this->audit->record([
            'module' => 'report',
            'action' => AuditLog::ACTION_EXPORT,
            'message' => "导出{$label}报表 CSV（".count($rows).' 行）',
            'context' => ['report' => 'profit_stock', 'section' => $section, 'rows' => count($rows)],
        ]);

        $filename = $label.'报表_'.now()->format('Ymd_Hi').'.csv';

        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ',', '"', '\\');
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
