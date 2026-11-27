<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| portal:leads —— 查看门户预约线索（S7-12 的"后台可查"）
|--------------------------------------------------------------------------
| 只读 portal.sqlite 的 leads 表，不碰任何产品库。
|
|   php artisan portal:leads                 最近 20 条
|   php artisan portal:leads --all           全部
|   php artisan portal:leads --status=new    只看未联系
|   php artisan portal:leads --mark=3        把第 3 条标为已联系（--note 可附备注）
*/
Artisan::command('portal:leads {--all} {--status=} {--limit=20} {--mark=} {--note=}', function () {
    $conn = DB::connection('portal');

    if ($this->option('mark') !== null) {
        $id = (int) $this->option('mark');
        $row = $conn->table('leads')->where('id', $id)->first();

        if ($row === null) {
            $this->error("线索 #{$id} 不存在。");

            return 1;
        }

        $conn->table('leads')->where('id', $id)->update([
            'status' => 'contacted',
            'note' => $this->option('note') !== null ? mb_substr((string) $this->option('note'), 0, 200) : $row->note,
            'updated_at' => now(),
        ]);

        $this->info("线索 #{$id}（{$row->name}）已标为「已联系」。");

        return 0;
    }

    $query = $conn->table('leads')->orderByDesc('id');

    if ($this->option('status') !== null) {
        $query->where('status', (string) $this->option('status'));
    }

    if (! $this->option('all')) {
        $query->limit(max(1, (int) $this->option('limit')));
    }

    $rows = $query->get();

    $counts = $conn->table('leads')
        ->selectRaw('status, count(*) as c')
        ->groupBy('status')
        ->pluck('c', 'status')
        ->all();

    $this->line('线索统计：' . (count($counts) > 0
        ? implode(' / ', array_map(static fn ($k, $v) => "{$k} {$v}", array_keys($counts), $counts))
        : '暂无'));

    if ($rows->isEmpty()) {
        $this->comment('没有线索。门户首页「预约演示」提交后会出现在这里。');

        return 0;
    }

    $this->line('');
    $this->line(sprintf('%-4s %-16s %-10s %-8s %-6s %s', 'ID', '时间', '称呼', '联系方式', '产品', '状态 / 内容'));

    foreach ($rows as $r) {
        $this->line(sprintf(
            '%-4s %-16s %-10s %-8s %-6s %s',
            $r->id,
            substr((string) $r->created_at, 0, 16),
            mb_substr((string) $r->name, 0, 8),
            mb_substr((string) $r->contact, 0, 16),
            $r->interest ?? 'all',
            $r->status . ($r->company ? ' / ' . mb_substr((string) $r->company, 0, 12) : '') . ($r->message ? '：' . mb_substr((string) $r->message, 0, 40) : ''),
        ));
    }

    return 0;
})->purpose('查看门户预约线索（只读 portal.sqlite）');
