<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

foreach (DB::connection('erp')->table('approval_flows')->get() as $f) {
    echo "flow {$f->id} {$f->flow_code} | {$f->business_type} | status={$f->status}\n  steps={$f->steps_json}\n";
}
echo "---\n";
foreach (DB::connection('erp')->table('approval_instances')->get() as $i) {
    echo "inst {$i->id} flow={$i->flow_id} biz={$i->business_type} step={$i->current_step} status={$i->status} applicant={$i->applicant_id} delegated={$i->delegated}\n";
}
