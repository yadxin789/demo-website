<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$db = DB::connection('mes');

echo "=== 每个 BOM 的缺料情况 ===\n";
$boms = $db->table('boms')->get();
foreach ($boms as $bom) {
    $lines = $db->table('bom_lines')->where('bom_id', $bom->id)->get();
    $short = [];
    $cost = 0.0;
    foreach ($lines as $l) {
        $mat = $db->table('materials')->where('id', $l->component_material_id)->first();
        $need = (float) $l->qty_per * (1 + (float) $l->loss_rate);
        $cost += $need * (float) $mat->std_cost;
        if ((float) $mat->stock_qty < $need) {
            $short[] = $mat->code . '（库存 ' . $mat->stock_qty . ' / 需 ' . round($need, 3) . '）';
        }
    }
    printf("  %-12s 行=%-2d 料本=%-8.2f 缺料=%d %s\n", $bom->code, count($lines), $cost, count($short), implode('、', $short));
}

echo "\n=== BOM 1 的组件 ===\n";
foreach ($db->table('bom_lines')->where('bom_id', 1)->orderBy('seq')->get() as $l) {
    $mat = $db->table('materials')->where('id', $l->component_material_id)->first();
    printf("  %-4d %-12s %-22s 用量=%s 损耗=%s 库存=%s\n", $l->seq, $mat->code, $mat->name, $l->qty_per, $l->loss_rate, $mat->stock_qty);
}

echo "\n=== 物料库存分布 ===\n";
foreach ($db->table('materials')->select('category', DB::raw('count(*) as n'), DB::raw('min(stock_qty) as mn'), DB::raw('max(stock_qty) as mx'))->groupBy('category')->get() as $r) {
    printf("  %-9s 条数=%-3d 库存区间 %s ~ %s\n", $r->category, $r->n, $r->mn, $r->mx);
}
