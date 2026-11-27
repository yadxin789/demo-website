<?php

declare(strict_types=1);

// 表结构对账：解析参考项目与演示站的 migrations，比对"表集合 + 列集合"。
// 用法：php tools/compare-schema.php erp|pm|shop [--all]

$code = $argv[1] ?? 'erp';
$showAll = in_array('--all', $argv, true);

$map = [
    'erp' => [
        'ref' => 'E:/ddwl-erp/erp/database/migrations',
        'demo' => __DIR__ . '/../app/Modules/Erp/Database/Migrations',
    ],
    'pm' => [
        'ref' => 'E:/my-pm/pm-server-openEuler/database/migrations',
        'demo' => __DIR__ . '/../app/Modules/Pm/Database/Migrations',
    ],
    'shop' => [
        'ref' => 'E:/erp-shop/database/migrations',
        'demo' => __DIR__ . '/../app/Modules/Shop/Database/Migrations',
    ],
];

if (! isset($map[$code])) {
    fwrite(STDERR, "未知产品：{$code}\n");
    exit(2);
}

/** 解析目录下所有 migration：返回 [表 => [列...]] */
function parseSchema(string $dir): array
{
    $out = [];
    if (! is_dir($dir)) {
        return $out;
    }

    foreach (glob($dir . '/*.php') as $file) {
        $text = (string) file_get_contents($file);

        preg_match_all("/Schema::(create|table)\(\s*'([a-zA-Z0-9_]+)'/", $text, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        foreach ($m as $match) {
            $table = $match[2][0];
            $start = $match[0][1];

            // 从 create/table 开始，截到该闭包的结束（粗略：到下一个 Schema:: 或文件尾）
            $next = strpos($text, 'Schema::', $start + 5);
            $chunk = substr($text, $start, ($next === false ? strlen($text) : $next) - $start);

            $out[$table] ??= [];
            preg_match_all("/\\\$table->[a-zA-Z]+\s*\(\s*'([a-zA-Z0-9_]+)'/", $chunk, $cols);
            foreach ($cols[1] as $col) {
                $out[$table][$col] = true;
            }
        }
    }

    foreach ($out as $t => $cols) {
        $out[$t] = array_keys($cols);
    }

    return $out;
}

$ref = parseSchema($map[$code]['ref']);
$demo = parseSchema($map[$code]['demo']);

echo "=== {$code} 表结构对账 ===\n";
echo '参考 ' . count($ref) . ' 表 / 演示站 ' . count($demo) . " 表\n\n";

$refTables = array_keys($ref);
$demoTables = array_keys($demo);
$missingTables = array_diff($refTables, $demoTables);

echo '参考有、演示站没有的表：' . count($missingTables) . "\n";
foreach ($missingTables as $t) {
    echo "  ✗ {$t}\n";
}

$missingCols = [];
$coveredTables = 0;
foreach (array_intersect($refTables, $demoTables) as $t) {
    $miss = array_diff($ref[$t], $demo[$t]);
    if ($miss !== []) {
        $missingCols[$t] = $miss;
    } else {
        $coveredTables++;
    }
}

echo "\n表都在、但列有缺的表：" . count($missingCols) . "\n";
$limit = $showAll ? 1000 : 15;
$i = 0;
foreach ($missingCols as $t => $cols) {
    if ($i++ >= $limit) {
        echo "  …（共 " . count($missingCols) . " 表，加 --all 看全量）\n";
        break;
    }
    echo "  ✗ {$t}：缺 " . implode(', ', $cols) . "\n";
}
echo "\n完全对齐（表与列都齐）的表：" . $coveredTables . "\n";

$buf = '';
foreach ($missingTables as $t) {
    $buf .= "MISSING_TABLE\t{$t}\n";
}
foreach ($missingCols as $t => $cols) {
    $buf .= "MISSING_COL\t{$t}\t" . implode(',', $cols) . "\n";
}
file_put_contents(__DIR__ . "/baseline/schema-{$code}.txt", $buf);
echo "\n明细已写入 tools/baseline/schema-{$code}.txt\n";

