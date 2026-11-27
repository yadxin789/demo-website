<?php

/**
 * 打印 ERP 全部枚举的取值（用于校对演示数据里的状态值）。
 */

require 'E:/demo-website/vendor/autoload.php';
$app = require 'E:/demo-website/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (glob('E:/demo-website/app/Modules/Erp/Enums/*.php') as $file) {
    $class = 'App\\Modules\\Erp\\Enums\\' . basename($file, '.php');
    if (! enum_exists($class)) {
        continue;
    }
    $values = array_map(fn ($c) => $c->value, $class::cases());
    echo str_pad(basename($file, '.php'), 28) . ': ' . implode(' | ', $values) . PHP_EOL;
}
