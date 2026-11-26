<?php

namespace App\Modules\Shop\Http\Controllers\Pos;

use App\Modules\Shop\Exceptions\PosException;
use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\Location;
use App\Modules\Shop\Models\PaymentMethod;
use App\Modules\Shop\Models\PosStation;
use App\Modules\Shop\Models\Warehouse;
use App\Modules\Shop\Services\Pos\ReceiptPrintService;
use App\Modules\Shop\Support\EscPosBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosStationController extends Controller
{
    public function index(): View
    {
        $stations = PosStation::with('warehouse')->orderBy('code')->get();

        return view('shop::pos.stations.index', compact('stations'));
    }

    public function create(): View
    {
        return $this->formView(new PosStation());
    }

    public function store(Request $request): RedirectResponse
    {
        $station = PosStation::create($this->validated($request));

        return redirect()->route('shop.pos.stations.index')->with('status', '收银台已创建');
    }

    public function edit(PosStation $station): View
    {
        return $this->formView($station);
    }

    public function update(Request $request, PosStation $station): RedirectResponse
    {
        $station->update($this->validated($request, $station->id));

        return redirect()->route('shop.pos.stations.index')->with('status', '收银台配置已保存');
    }

    /**
     * 打印测试页：生成固定测试小票指令并下发代理。
     */
    public function testPrint(PosStation $station, ReceiptPrintService $printer): RedirectResponse
    {
        try {
            $columns = (int) $station->paper_width === 80 ? 48 : 32;
            $bytes = (new EscPosBuilder($station->encoding, $columns))->build([
                'store_name' => config('shop.name', '收银小票'),
                'order_code' => 'TEST',
                'order_date' => now()->format('Y-m-d H:i'),
                'cashier' => auth()->user()->name ?? '—',
                'customer' => '',
                'items' => [
                    ['name' => '打印测试页', 'code' => 'TEST', 'qty' => 1, 'price' => 0, 'amount' => 0],
                ],
                'discount' => 0,
                'tax' => 0,
                'total' => 0,
                'payments' => [],
                'change' => 0,
            ])->bytes();

            $printer->dispatch($bytes, $station);

            return back()->with('status', '测试页已下发到打印机');
        } catch (PosException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * 触发钱箱脉冲。
     */
    public function testDrawer(PosStation $station, ReceiptPrintService $printer): RedirectResponse
    {
        try {
            $printer->openDrawer($station);

            return back()->with('status', '钱箱已弹出');
        } catch (PosException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    private function formView(PosStation $station): View
    {
        $warehouses = Warehouse::orderBy('code')->get();
        $locations = Location::orderBy('code')->get();
        $methods = PaymentMethod::active()->get();

        return view('shop::pos.stations.edit', compact('station', 'warehouses', 'locations', 'methods'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $id = null): array
    {
        $unique = $id
            ? "unique:pos_stations,code,{$id},id"
            : 'unique:pos_stations,code';

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', $unique],
            'name' => ['required', 'string', 'max:255'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'default_location_id' => ['nullable', 'exists:locations,id'],
            'payment_method_ids' => ['nullable', 'array'],
            'payment_method_ids.*' => ['integer', 'exists:payment_methods,id'],
            'printer_driver' => ['nullable', 'in:escpos,browser'],
            'printer_address' => ['nullable', 'string', 'max:255'],
            'printer_port' => ['nullable', 'string', 'max:32'],
            'paper_width' => ['nullable', 'in:58,80'],
            'encoding' => ['nullable', 'in:gbk,gb2312,utf-8'],
            'cash_drawer_pin' => ['nullable', 'string', 'max:16'],
            'auto_print_receipt' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:active,disabled'],
        ]);

        $data['payment_method_ids'] = $data['payment_method_ids'] ?? [];
        $data['auto_print_receipt'] = $request->boolean('auto_print_receipt');
        $data['printer_driver'] = $data['printer_driver'] ?? 'escpos';
        $data['paper_width'] = (int) ($data['paper_width'] ?? 58);
        $data['encoding'] = $data['encoding'] ?? 'gbk';
        $data['status'] = $data['status'] ?? PosStation::STATUS_ACTIVE;

        return $data;
    }
}
