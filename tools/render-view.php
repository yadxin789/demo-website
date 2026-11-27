<?php

/**
 * 渲染指定视图，捕获真实异常（用于排查页面上数据后出现的 500）。
 *
 *   php tools/render-view.php erp::hr.leaves
 */

require 'E:/demo-website/vendor/autoload.php';
$app = require 'E:/demo-website/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$view = $argv[1] ?? 'erp::hr.leaves';

$db = DB::connection('erp');

if ($view === 'erp::hr.leaves') {
    $leaves = App\Modules\Erp\Models\Hr\HrLeave::with('employee')->orderByDesc('id')->paginate(15);
    $emps = App\Modules\Erp\Models\Hr\HrEmployee::where('status', 'active')->get();
    $data = compact('leaves', 'emps');
} elseif ($view === 'erp::wecom.index') {
    $config = App\Modules\Erp\Models\System\WecomConfig::first();
    $logs = App\Modules\Erp\Models\System\WecomMessageLog::orderByDesc('id')->paginate(15);
    $data = compact('config', 'logs');
} else {
    fwrite(STDERR, "未配置该视图的渲染数据：{$view}\n");
    exit(1);
}

try {
    $html = view($view, $data)->render();
    echo '渲染成功，长度 ' . strlen($html) . PHP_EOL;
} catch (Throwable $e) {
    echo get_class($e) . ': ' . $e->getMessage() . PHP_EOL;
    echo '文件: ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
