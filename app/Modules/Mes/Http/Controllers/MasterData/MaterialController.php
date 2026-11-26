<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers\MasterData;

use App\Modules\Mes\Http\Controllers\Controller;
use App\Modules\Mes\Http\Requests\Master\MaterialRequest;
use App\Modules\Mes\Models\Material;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 物料主数据（M1-01）。
 *
 * 列表支持编码/名称搜索、分类筛选、分页；新增与编辑用同一页的模态框。
 * 只读写 mes 库，不触碰其它产品（MC-03）。
 */
class MaterialController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', '');

        $materials = Material::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
                });
            })
            ->when(in_array($category, ['raw', 'semi', 'finished'], true), function ($query) use ($category) {
                $query->where('category', $category);
            })
            ->orderBy('code')
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total' => Material::query()->count(),
            'finished' => Material::query()->where('category', 'finished')->count(),
            'semi' => Material::query()->where('category', 'semi')->count(),
            'raw' => Material::query()->where('category', 'raw')->count(),
            'low' => Material::query()->whereColumn('stock_qty', '<', 'safety_stock')->count(),
        ];

        return view('mes::master.materials.index', [
            'materials' => $materials,
            'q' => $q,
            'category' => $category,
            'stats' => $stats,
        ]);
    }

    public function store(MaterialRequest $request): RedirectResponse
    {
        Material::query()->create($request->validated() + ['is_active' => true]);

        return redirect()->route('mes.master.materials.index')->with('status', '物料已新增。');
    }

    public function update(MaterialRequest $request, Material $material): RedirectResponse
    {
        $material->fill($request->validated());
        $material->is_active = $request->boolean('is_active');
        $material->save();

        return redirect()->route('mes.master.materials.index')->with('status', '物料已更新。');
    }
}
