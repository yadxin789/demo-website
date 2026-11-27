<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\MasterData;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Http\Requests\Master\BomLineRequest;
use App\Modules\Mes\Http\Requests\Master\BomRequest;
use App\Modules\Mes\Models\Bom;
use App\Modules\Mes\Models\BomLine;
use App\Modules\Mes\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * BOM（M1-02）：单层 BOM 的维护。
 *
 * 设计取舍：只做一层（产品 → 组件），不做多层展开。演示站讲清"产品由哪些料组成、
 * 用量多少、损耗多少、单件成本多少"就够了；多层 BOM 会把界面复杂度抬上去，
 * 对 5 分钟走查没有收益。
 */
class BomController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');

        $boms = Bom::query()
            ->with(['product:id,code,name,unit,category', 'lines.material:id,code,name,unit,std_cost,stock_qty'])
            ->withCount('lines')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('code', 'like', "%{$q}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$q}%")
                            ->orWhere('code', 'like', "%{$q}%"));
                });
            })
            ->when(in_array($status, ['active', 'archived'], true), fn ($query) => $query->where('status', $status))
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        $rows = $boms->getCollection()->map(function (Bom $bom) {
            $cost = 0.0;
            $short = 0;
            foreach ($bom->lines as $line) {
                $need = (float) $line->qty_per * (1 + (float) $line->loss_rate);
                $cost += $need * (float) ($line->material?->std_cost ?? 0);
                if ($line->material && (float) $line->material->stock_qty < $need) {
                    $short++;
                }
            }

            return [
                'bom' => $bom,
                'cost' => $cost,
                'short' => $short,
            ];
        });

        return view('mes::master.boms.index', [
            'boms' => $boms,
            'rows' => $rows,
            'q' => $q,
            'status' => $status,
            'products' => Material::query()
                ->whereIn('category', ['finished', 'semi'])
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'category']),
            'stat' => [
                'count' => Bom::query()->count(),
                'active' => Bom::query()->where('status', 'active')->count(),
                'lines' => BomLine::query()->count(),
                'short' => $rows->where('short', '>', 0)->count(),
            ],
        ]);
    }

    public function store(BomRequest $request): RedirectResponse
    {
        $bom = Bom::query()->create($request->validated());

        return redirect()
            ->route('mes.master.boms.show', $bom)
            ->with('status', "BOM {$bom->code} 已创建，现在往里面加组件。");
    }

    public function show(Bom $bom): View
    {
        $bom->load(['product:id,code,name,unit,category,std_cost', 'lines.material:id,code,name,unit,std_cost,stock_qty,safety_stock']);

        $cost = 0.0;
        $lines = $bom->lines->map(function (BomLine $line) use (&$cost) {
            $need = (float) $line->qty_per * (1 + (float) $line->loss_rate);
            $amount = $need * (float) ($line->material?->std_cost ?? 0);
            $cost += $amount;

            return [
                'line' => $line,
                'need' => $need,
                'amount' => $amount,
                'lack' => $line->material ? (float) $line->material->stock_qty < $need : false,
            ];
        });

        return view('mes::master.boms.show', [
            'bom' => $bom,
            'lines' => $lines,
            'cost' => $cost,
            'components' => Material::query()
                ->whereIn('category', ['raw', 'semi'])
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'unit', 'std_cost', 'stock_qty']),
        ]);
    }

    public function update(BomRequest $request, Bom $bom): RedirectResponse
    {
        $bom->update($request->validated());

        return redirect()->route('mes.master.boms.show', $bom)->with('status', 'BOM 头信息已更新。');
    }

    public function destroy(Bom $bom): RedirectResponse
    {
        $code = $bom->code;
        $bom->lines()->delete();
        $bom->delete();

        return redirect()->route('mes.master.boms.index')->with('status', "BOM {$code} 已删除（含全部组件行）。");
    }

    public function addLine(BomLineRequest $request, Bom $bom): RedirectResponse
    {
        $data = $request->validated();

        if ($bom->lines()->where('component_material_id', $data['component_material_id'])->exists()) {
            return back()->withErrors(['component_material_id' => '这个物料已经在本 BOM 里了，请直接改用量。'])->withInput();
        }

        $bom->lines()->create([
            'component_material_id' => $data['component_material_id'],
            'qty_per' => $data['qty_per'],
            'loss_rate' => $data['loss_rate'] ?? 0,
            'seq' => $data['seq'] ?? ((int) $bom->lines()->max('seq') + 10),
        ]);

        return redirect()->route('mes.master.boms.show', $bom)->with('status', '组件行已添加。');
    }

    public function updateLine(BomLineRequest $request, Bom $bom, BomLine $line): RedirectResponse
    {
        abort_unless($line->bom_id === $bom->id, 404);

        $line->update($request->validated());

        return redirect()->route('mes.master.boms.show', $bom)->with('status', '组件行已更新。');
    }

    public function destroyLine(Bom $bom, BomLine $line): RedirectResponse
    {
        abort_unless($line->bom_id === $bom->id, 404);
        $line->delete();

        return redirect()->route('mes.master.boms.show', $bom)->with('status', '组件行已删除。');
    }
}
