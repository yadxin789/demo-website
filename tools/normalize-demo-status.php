<?php

/**
 * 演示数据校准（ERP）：把 DemoCoverage* 写入的状态值改成代码枚举的合法值，并补齐「按用户 / 按状态」过滤的列表所需数据。
 *
 * 幂等、只补不改业务主数据；可在本地与线上反复执行：
 *   php tools/normalize-demo-status.php
 */

$base = dirname(__DIR__);
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$db = DB::connection('erp');
$now = now()->format('Y-m-d H:i:s');

$map = [
    'sales_quotes' => ['status' => ['accepted' => 'converted', 'sent' => 'confirmed', 'rejected' => 'expired']],
    'sales_orders' => ['status' => ['in_production' => 'picking']],
    'shipments' => ['status' => ['in_transit' => 'shipped']],
    'customer_returns' => ['status' => ['approved' => 'closed', 'processing' => 'inspecting']],
    'purchase_orders' => ['status' => ['completed' => 'closed']],
    'goods_receipts' => ['status' => ['completed' => 'closed', 'inspecting' => 'pending_qc']],
    'inspection_records' => ['result' => ['passed' => 'accepted', 'failed' => 'rejected']],
    'bom_imports' => ['status' => ['completed' => 'imported', 'parsing' => 'parsed']],
    'customer_invoices' => ['status' => ['open' => 'unpaid']],
    'supplier_bills' => ['status' => ['open' => 'unpaid']],
    'crm_leads' => ['status' => ['following' => 'contacted']],
    'crm_opportunities' => ['stage' => ['negotiating' => 'negotiation', 'proposal' => 'prospecting', 'qualifying' => 'prospecting', 'closing' => 'negotiation']],
    'aftersales_tickets' => ['status' => ['pending' => 'open']],
    'tenants' => ['status' => ['disabled' => 'expired']],
    'sync_tasks' => ['status' => ['running' => 'active']],
    'voice_rooms' => ['status' => ['active' => 'ongoing']],
    'procurement_plans' => ['status' => ['completed' => 'received']],
    'announcements' => ['status' => ['published' => 1]],
];

foreach ($map as $table => $cols) {
    if (! Schema::connection('erp')->hasTable($table)) {
        continue;
    }
    foreach ($cols as $col => $pairs) {
        foreach ($pairs as $from => $to) {
            $n = $db->table($table)->where($col, $from)->update([$col => $to]);
            if ($n > 0) {
                echo "  {$table}.{$col}: {$from} → {$to}（{$n} 行）" . PHP_EOL;
            }
        }
    }
}

// MRP：控制器只认 open / converted
$db->table('mrp_suggestions')->where('status', 'suggested')->update(['status' => 'open']);
$db->table('mrp_suggestions')->where('id', 6)->update(['status' => 'converted']);
echo '  mrp_suggestions: suggested → open / converted' . PHP_EOL;

// 收货待上架队列：需要 qc_passed
$db->table('goods_receipts')->where('receipt_no', 'GR-DM-002')->update(['status' => 'qc_passed']);
echo '  goods_receipts: GR-DM-002 → qc_passed（上架队列）' . PHP_EOL;

// BOM 待审：需要 draft
if (! $db->table('bom_headers')->where('bom_code', 'DM-BOM-DRAFT')->exists()) {
    $db->table('bom_headers')->insert([
        'bom_code' => 'DM-BOM-DRAFT',
        'product_material_id' => 10,
        'version' => 'V0',
        'status' => 'draft',
        'effective_date' => now()->format('Y-m-d') . ' 00:00:00',
        'remark' => '演示：待审核 BOM',
        'created_by' => 8,
        'approved_by' => null,
        'approved_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $bid = (int) $db->table('bom_headers')->where('bom_code', 'DM-BOM-DRAFT')->value('id');
    $db->table('bom_lines')->insert([
        ['bom_header_id' => $bid, 'parent_material_id' => 10, 'material_id' => 1, 'qty_per' => 4, 'unit' => 'PCS', 'ref_des' => 'R1-R4', 'loss_rate' => 0, 'is_alternative' => 0, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['bom_header_id' => $bid, 'parent_material_id' => 10, 'material_id' => 2, 'qty_per' => 2, 'unit' => 'PCS', 'ref_des' => 'C1-C2', 'loss_rate' => 0, 'is_alternative' => 0, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
        ['bom_header_id' => $bid, 'parent_material_id' => 10, 'material_id' => 6, 'qty_per' => 1, 'unit' => 'SET', 'ref_des' => null, 'loss_rate' => 0, 'is_alternative' => 0, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
    ]);
    echo '  bom_headers: 新增 1 条 draft（待审 BOM）' . PHP_EOL;
}

// 知识库 / 资源文件页读 media_files 的 category
$existing = $db->table('media_files')->pluck('category')->unique()->all();
if (! in_array('collab_doc', $existing, true)) {
    $db->table('media_files')->insert([
        ['file_name' => 'SMT 工艺规范.pdf', 'file_path' => 'demo/doc/smt-spec.pdf', 'file_size' => 512000, 'mime_type' => 'application/pdf', 'category' => 'collab_doc', 'uploader_id' => 8, 'ref_type' => null, 'ref_id' => null, 'created_at' => $now],
        ['file_name' => '来料检验标准.docx', 'file_path' => 'demo/doc/iqc-std.docx', 'file_size' => 128000, 'mime_type' => 'application/msword', 'category' => 'collab_doc', 'uploader_id' => 9, 'ref_type' => null, 'ref_id' => null, 'created_at' => $now],
        ['file_name' => '产线布局图.png', 'file_path' => 'demo/doc/layout.png', 'file_size' => 204800, 'mime_type' => 'image/png', 'category' => 'collab_doc', 'uploader_id' => 8, 'ref_type' => null, 'ref_id' => null, 'created_at' => $now],
    ]);
    echo '  media_files: 新增 3 条 collab_doc（知识库）' . PHP_EOL;
}
if (! in_array('resource', $existing, true)) {
    $db->table('media_files')->insert([
        ['file_name' => '客户 A 年度框架合同.pdf', 'file_path' => 'demo/res/contract-a.pdf', 'file_size' => 320000, 'mime_type' => 'application/pdf', 'category' => 'resource', 'uploader_id' => 5, 'ref_type' => null, 'ref_id' => null, 'created_at' => $now],
        ['file_name' => '产品认证证书.pdf', 'file_path' => 'demo/res/cert.pdf', 'file_size' => 180000, 'mime_type' => 'application/pdf', 'category' => 'resource', 'uploader_id' => 8, 'ref_type' => null, 'ref_id' => null, 'created_at' => $now],
        ['file_name' => '设备操作手册.pdf', 'file_path' => 'demo/res/manual.pdf', 'file_size' => 640000, 'mime_type' => 'application/pdf', 'category' => 'resource', 'uploader_id' => 6, 'ref_type' => null, 'ref_id' => null, 'created_at' => $now],
    ]);
    echo '  media_files: 新增 3 条 resource（资源文件）' . PHP_EOL;
}

// 待检批次（质量待办页）：inventory_stock 里 qc_hold 且 qty>0
if ($db->table('inventory_stock')->where('status', 'qc_hold')->count() === 0) {
    $db->table('inventory_stock')->insert([
        ['material_id' => 3, 'lot_no' => 'LOT20261126', 'warehouse_id' => 1, 'location_id' => 2, 'status' => 'qc_hold', 'qty' => 800, 'reserved_qty' => 0, 'receive_date' => now()->subDay()->format('Y-m-d') . ' 00:00:00', 'supplier_batch' => 'B20261126', 'created_at' => $now, 'updated_at' => $now],
        ['material_id' => 8, 'lot_no' => 'LOT20261125', 'warehouse_id' => 1, 'location_id' => 2, 'status' => 'qc_hold', 'qty' => 300, 'reserved_qty' => 0, 'receive_date' => now()->subDays(2)->format('Y-m-d') . ' 00:00:00', 'supplier_batch' => 'B20261125', 'created_at' => $now, 'updated_at' => $now],
    ]);
    echo '  inventory_stock: 新增 2 条 qc_hold（待检批次）' . PHP_EOL;
}

// admin 视角的通知与待办
$adminId = (int) ($db->table('users')->where('username', 'admin')->value('id') ?? 0);
if ($adminId > 0) {
    if (! $db->table('system_notifications')->where('user_id', $adminId)->exists()) {
        $rows = [];
        foreach ($db->table('system_notifications')->where('user_id', 1)->get() as $r) {
            $rows[] = [
                'user_id' => $adminId,
                'notify_type' => $r->notify_type,
                'title' => $r->title,
                'content' => $r->content,
                'is_read' => $r->is_read,
                'business_type' => $r->business_type,
                'business_id' => $r->business_id,
                'created_at' => $r->created_at,
            ];
        }
        if ($rows !== []) {
            $db->table('system_notifications')->insert($rows);
            echo "  system_notifications: 为 admin({$adminId}) 补 " . count($rows) . ' 条' . PHP_EOL;
        }
    }

    if (! $db->table('system_todo_items')->where('user_id', $adminId)->exists()) {
        $rows = [];
        foreach ($db->table('system_todo_items')->where('user_id', 1)->get() as $r) {
            $rows[] = [
                'user_id' => $adminId,
                'todo_type' => $r->todo_type,
                'title' => $r->title,
                'deadline' => $r->deadline,
                'is_finished' => $r->is_finished,
                'business_type' => $r->business_type,
                'business_id' => $r->business_id,
                'created_at' => $r->created_at,
                'finished_at' => $r->finished_at,
            ];
        }
        if ($rows !== []) {
            $db->table('system_todo_items')->insert($rows);
            echo "  system_todo_items: 为 admin({$adminId}) 补 " . count($rows) . ' 条' . PHP_EOL;
        }
    }
}

// 支援页：todo_type 含 support/assist
if ($db->table('system_todo_items')->where('todo_type', 'like', '%support%')->count() === 0) {
    $db->table('system_todo_items')->insert([
        ['user_id' => $adminId, 'todo_type' => 'support', 'title' => '支援：协助仓库月末盘点', 'deadline' => now()->addDays(2)->format('Y-m-d') . ' 18:00:00', 'is_finished' => 0, 'business_type' => 'stocktake', 'business_id' => 2, 'created_at' => $now, 'finished_at' => null],
        ['user_id' => 9, 'todo_type' => 'assist', 'title' => '支援：协助品质部全检返工批次', 'deadline' => now()->addDay()->format('Y-m-d') . ' 18:00:00', 'is_finished' => 0, 'business_type' => 'inspection', 'business_id' => 2, 'created_at' => $now, 'finished_at' => null],
    ]);
    echo '  system_todo_items: 新增 2 条 support/assist' . PHP_EOL;
}

// 研发项目：让 admin 也能看到"我的项目"
if ($adminId > 0 && ! $db->table('rnd_projects')->where('creator_id', $adminId)->exists()) {
    $db->table('rnd_projects')->where('project_code', 'RP-DM-003')->update(['creator_id' => $adminId]);
    echo "  rnd_projects: RP-DM-003 creator → admin({$adminId})" . PHP_EOL;
}

// 集成出站（events/outbox）
if (Schema::connection('erp')->hasTable('integration_outbox') && $db->table('integration_outbox')->count() === 0) {
    $db->table('integration_outbox')->insert([
        ['event_type' => 'sales_order.confirmed', 'payload' => json_encode(['order_no' => 'SO-DM-003'], JSON_UNESCAPED_UNICODE), 'status' => 'pending', 'retry_count' => 0, 'created_at' => $now, 'updated_at' => $now],
        ['event_type' => 'goods_receipt.created', 'payload' => json_encode(['receipt_no' => 'GR-DM-001'], JSON_UNESCAPED_UNICODE), 'status' => 'published', 'retry_count' => 0, 'created_at' => $now, 'updated_at' => $now],
        ['event_type' => 'production_order.released', 'payload' => json_encode(['order_no' => 'WO-DM-003'], JSON_UNESCAPED_UNICODE), 'status' => 'failed', 'retry_count' => 2, 'created_at' => $now, 'updated_at' => $now],
    ]);
    echo '  integration_outbox: 新增 3 条' . PHP_EOL;
}

echo '校准完成' . PHP_EOL;
