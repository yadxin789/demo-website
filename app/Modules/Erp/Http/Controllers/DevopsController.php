<?php

namespace App\Modules\Erp\Http\Controllers;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DevopsController extends Controller
{
    public function index()
    {
        // P0 修复：不再暴露 PHP 版本 / DB 名 / APP_ENV / debug 开关等敏感信息
        $info = [
            '应用名称' => config('app.name'),
            '运行环境' => app()->environment() === 'local' ? '开发环境' : '生产环境',
            '时区' => config('app.timezone'),
            '缓存驱动' => config('cache.default'),
        ];

        try {
            DB::select('select 1 as ok');
            $dbStatus = '✅ 连接正常';
        } catch (\DomainException|\InvalidArgumentException $e) {
            $dbStatus = '❌ 连接失败';
            Log::error('Devops 页面报告数据库连接失败', ['err' => $e->getMessage()]);
        }

        $tables = $this->tableCount();
        $migrations = DB::table('migrations')->count();

        return view('erp::devops.index', compact('info', 'dbStatus', 'tables', 'migrations'));
    }

    /** 表数量：MySQL 走 SHOW TABLES，SQLite（本地演示）走 sqlite_master */
    private function tableCount(): int
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("select count(*) as c from sqlite_master where type = 'table' and name not like 'sqlite_%'");

            return (int) ($rows[0]->c ?? 0);
        }

        if ($driver === 'pgsql') {
            $rows = DB::select("select count(*) as c from information_schema.tables where table_schema = 'public'");

            return (int) ($rows[0]->c ?? 0);
        }

        return count(DB::select('SHOW TABLES'));
    }

    public function clearCache()
    {
        Artisan::call('cache:clear');
        Artisan::call('view:clear');
        Artisan::call('route:clear');

        Log::info('管理员清理了应用缓存', ['user_id' => auth()->id()]);

        return back()->with('success', '缓存已清理。');
    }
}
