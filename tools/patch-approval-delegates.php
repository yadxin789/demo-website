<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

$adminId = 30;
$flows = DB::connection('erp')->table('approval_flows')->get();
foreach ($flows as $f) {
    $steps = json_decode($f->steps_json, true) ?: [];
    $changed = false;
    foreach ($steps as &$s) {
        $d = array_map('intval', $s['delegate_ids'] ?? []);
        if (!in_array($adminId, $d, true)) { $d[] = $adminId; $s['delegate_ids'] = $d; $changed = true; }
    }
    unset($s);
    if ($changed) {
        DB::connection('erp')->table('approval_flows')->where('id', $f->id)->update(['steps_json' => json_encode($steps, JSON_UNESCAPED_UNICODE)]);
        echo "flow {$f->id} {$f->flow_code} 已追加代批人 {$adminId}\n";
    }
}
echo "done\n";
