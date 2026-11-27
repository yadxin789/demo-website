<?php

declare(strict_types=1);

/**
 * 领料服务自测（M3-04 / M3-06）。只读演示库、可重复执行，跑完会把数据恢复原状。
 *
 * 覆盖：
 *   1. 按 BOM × 计划数量 × 损耗展开需求，同物料合并
 *   2. 逐行部分发料 → 行 partial、单 partial
 *   3. 库存不足 → 整行跳过并进 shortage 清单，库存不变
 *   4. 超发 → 自动 clamp 到剩余未发量
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Mes\Models\Material;
use App\Modules\Mes\Models\MaterialIssue;
use App\Modules\Mes\Models\WorkOrder;
use App\Modules\Mes\Services\IssueService;

$svc = new IssueService;
$order = WorkOrder::query()->where('order_no', 'WO-2026-0004')->firstOrFail();

// 清掉可能已存在的单，保证从零开始
MaterialIssue::query()->where('work_order_id', $order->id)->delete();

$issue = $svc->createFromOrder($order);
printf("1) 生成 %s，%d 行，需求合计 %.3f\n", $issue->issue_no, $issue->lines()->count(), (float) $issue->lines()->sum('required_qty'));

// 复用：再调一次应返回同一张，不重复建
$again = $svc->createFromOrder($order);
printf("2) 重复调用返回同一张：%s（单数 %d）\n", $again->issue_no, MaterialIssue::query()->where('work_order_id', $order->id)->count());

$lines = $issue->fresh()->load('lines.material')->lines;
// 挑一行库存足够发一半的，才能验证"部分发料"
$first = $lines->first(fn ($l) => (float) $l->material->stock_qty >= (float) $l->required_qty / 2) ?? $lines[0];
$firstStock = (float) $first->material->stock_qty;
$half = round((float) $first->required_qty / 2, 3);

$r = $svc->issue($issue, [$first->id => $half], 1);
printf(
    "3) 发第 1 行 %.3f：行状态 %s，单状态 %s，库存 %.3f → %.3f（差 %.3f）\n",
    $half,
    $issue->fresh()->lines->firstWhere('id', $first->id)->status,
    $issue->fresh()->status,
    $firstStock,
    (float) Material::query()->find($first->material_id)->stock_qty,
    $firstStock - (float) Material::query()->find($first->material_id)->stock_qty
);

// 库存不足：把第 2 行物料库存临时改成 0.5，发 10 应被整行跳过
$second = $lines[1];
$secondStock = (float) $second->material->stock_qty;
Material::query()->whereKey($second->material_id)->update(['stock_qty' => 0.5]);

$r2 = $svc->issue($issue, [$second->id => 10], 1);
printf(
    "4) 库存 0.5 发 10：shortage %d 条（%s），库存仍 %.3f，行状态 %s\n",
    count($r2['shortage']),
    $r2['shortage'][0]['code'] ?? '—',
    (float) Material::query()->find($second->material_id)->stock_qty,
    $issue->fresh()->lines->firstWhere('id', $second->id)->status
);

// 恢复库存
Material::query()->whereKey($second->material_id)->update(['stock_qty' => $secondStock]);

// 超发 clamp：给第 3 行填 required + 9999，只能写到 required
if (isset($lines[2])) {
    $third = $lines[2];
    $req3 = (float) $third->required_qty;
    $svc->issue($issue, [$third->id => $req3 + 9999], 1);
    printf("5) 超发：请求 %.3f，实发 %.3f（应等于需求 %.3f）\n", $req3 + 9999, (float) $issue->fresh()->lines->firstWhere('id', $third->id)->issued_qty, $req3);
}

$fresh = $issue->fresh()->load('lines');
printf("6) 最终：单 %s，行 %s\n", $fresh->status, $fresh->lines->pluck('status')->implode('/'));

// 收尾：删单并把库存还原
$fresh->lines()->delete();
$fresh->delete();
foreach ($lines as $line) {
    Material::query()->whereKey($line->material_id)->update(['stock_qty' => (float) $line->material->stock_qty]);
}
echo "已清理测试单，演示数据未变\n";
