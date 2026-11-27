<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Support\System\SystemConsole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 五产品统一的「系统管理后台」。
 *
 * 只做组织与账号（用户 / 部门 / 岗位 / 角色）、AI 导入引导、运维监督、系统设置，
 * 不承载任何业务单据操作，也不提供业务入口。
 */
class SystemConsoleController extends Controller
{
    private string $product = 'erp';

    private array $cfg = [];

    private function boot(Request $request): void
    {
        $this->product = (string) ($request->route('product') ?? $request->segment(1) ?? 'erp');
        abort_unless(isset(SystemConsole::PRODUCTS[$this->product]), 404);
        $this->cfg = SystemConsole::config($this->product);

        $user = Auth::guard(SystemConsole::guard($this->product))->user();
        if (! SystemConsole::canAdmin($this->product, $user)) {
            abort(403, '该账号没有系统管理权限');
        }
        view()->share('product', $this->product);
        view()->share('cfg', $this->cfg);
        view()->share('consoleBase', "/{$this->product}/admin");
        view()->share('currentAdmin', $user);
        view()->share('themeColor', SystemConsole::theme($this->product));
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());
    }

    public function dashboard(Request $request)
    {
        $this->boot($request);
        $stats = SystemConsole::stats($this->product);
        $recentUsers = SystemConsole::users($this->product, null, 8);
        $roles = SystemConsole::roles($this->product);

        return view('system.dashboard', compact('stats', 'recentUsers', 'roles'));
    }

    public function users(Request $request)
    {
        $this->boot($request);
        $keyword = (string) $request->query('q', '');
        $users = SystemConsole::users($this->product, $keyword);

        return view('system.users', compact('users', 'keyword'));
    }

    public function userToggle(Request $request, int $id)
    {
        $this->boot($request);
        [$col, $activeVal] = SystemConsole::userStatus($this->product);
        $row = DB::connection($this->product)->table('users')->where('id', $id)->first();
        abort_if(! $row, 404);

        $current = $row->{$col};
        if (is_numeric($activeVal)) {
            $next = ((int) $current === (int) $activeVal) ? 0 : (int) $activeVal;
        } else {
            $next = ((string) $current === (string) $activeVal) ? 'disabled' : $activeVal;
        }
        DB::connection($this->product)->table('users')->where('id', $id)->update([$col => $next]);

        return back()->with('success', '账号状态已更新');
    }

    public function departments(Request $request)
    {
        $this->boot($request);
        $departments = SystemConsole::departments($this->product);
        $hasTable = SystemConsole::hasTable($this->product, 'departments');

        return view('system.departments', compact('departments', 'hasTable'));
    }

    public function departmentStore(Request $request)
    {
        $this->boot($request);
        abort_unless(SystemConsole::hasTable($this->product, 'departments'), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'code' => ['nullable', 'string', 'max:32'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->insertOrg('departments', $data);

        return back()->with('success', '部门已创建');
    }

    public function departmentUpdate(Request $request, int $id)
    {
        $this->boot($request);
        $data = $request->validate([
            'sort' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]);
        $this->updateOrg('departments', $id, $data);

        return back()->with('success', '部门已更新');
    }

    public function positions(Request $request)
    {
        $this->boot($request);
        $positions = SystemConsole::positions($this->product);
        $departments = SystemConsole::departments($this->product);
        $hasTable = SystemConsole::hasTable($this->product, 'positions');

        return view('system.positions', compact('positions', 'departments', 'hasTable'));
    }

    public function positionStore(Request $request)
    {
        $this->boot($request);
        abort_unless(SystemConsole::hasTable($this->product, 'positions'), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'code' => ['nullable', 'string', 'max:32'],
            'department_id' => ['nullable', 'integer'],
        ]);
        $this->insertOrg('positions', $data);

        return back()->with('success', '岗位已创建');
    }

    public function positionUpdate(Request $request, int $id)
    {
        $this->boot($request);
        $data = $request->validate([
            'active' => ['nullable', 'boolean'],
        ]);
        $this->updateOrg('positions', $id, $data);

        return back()->with('success', '岗位已更新');
    }

    public function roles(Request $request)
    {
        $this->boot($request);
        $roles = SystemConsole::roles($this->product);

        return view('system.roles', compact('roles'));
    }

    public function aiGuide(Request $request)
    {
        $this->boot($request);
        $fields = [
            '用户' => $this->columnsOf('users', ['password', 'remember_token', 'mfa_secret', 'mfa_recovery_codes']),
            '部门' => $this->columnsOf('departments', []),
            '岗位' => $this->columnsOf('positions', []),
            '角色' => SystemConsole::hasTable($this->product, 'spatie_roles') ? $this->columnsOf('spatie_roles', []) : $this->columnsOf('roles', []),
        ];
        $prompt = $this->buildPrompt($fields);

        return view('system.ai-guide', compact('fields', 'prompt'));
    }

    public function ops(Request $request)
    {
        $this->boot($request);
        $conn = DB::connection($this->product);
        $tables = $conn->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
        $rowCounts = [];
        $totalRows = 0;
        foreach ($tables as $t) {
            try {
                $c = $conn->table($t->name)->count();
            } catch (\Throwable) {
                $c = 0;
            }
            $rowCounts[$t->name] = $c;
            $totalRows += $c;
        }
        arsort($rowCounts);
        $topTables = array_slice($rowCounts, 0, 12, true);

        $checks = [
            ['项目', 'PHP 版本', PHP_VERSION, PHP_VERSION_ID >= 80300 ? 'ok' : 'warn'],
            ['项目', 'Laravel 版本', app()->version(), 'ok'],
            ['项目', '运行环境', app()->environment(), app()->environment() === 'production' ? 'ok' : 'info'],
            ['数据库', '连接驱动', $conn->getDriverName(), 'ok'],
            ['数据库', '库文件', SystemConsole::databaseFile($this->product), 'ok'],
            ['数据库', '库大小', $this->human(SystemConsole::databaseSize($this->product)), 'ok'],
            ['数据库', '数据表', count($tables) . ' 张', 'ok'],
            ['数据库', '总记录数', number_format($totalRows), 'ok'],
            ['缓存', '缓存驱动', (string) config('cache.default'), 'ok'],
            ['队列', '队列驱动', (string) config('queue.default'), config('queue.default') === 'sync' ? 'warn' : 'ok'],
            ['会话', '会话驱动', (string) config('session.driver'), 'ok'],
            ['存储', '磁盘剩余', $this->human((int) @disk_free_space(base_path())), 'ok'],
            ['日志', '日志文件大小', $this->human((int) @filesize(storage_path('logs/laravel.log'))), 'ok'],
        ];

        $logTail = $this->logTail();

        return view('system.ops', compact('checks', 'topTables', 'logTail'));
    }

    public function settings(Request $request)
    {
        $this->boot($request);
        $productCfg = config("products.products.{$this->product}", []);
        $env = [
            'APP_ENV' => config('app.env'),
            'APP_URL' => config('app.url'),
            'APP_TIMEZONE' => config('app.timezone'),
            'APP_LOCALE' => config('app.locale'),
            'CACHE_STORE' => config('cache.default'),
            'SESSION_DRIVER' => config('session.driver'),
            'QUEUE_CONNECTION' => config('queue.default'),
            'MAIL_MAILER' => config('mail.default'),
            'MAIL_FROM' => config('mail.from.address'),
            'DB_CONNECTION' => config("database.connections.{$this->product}.driver"),
        ];

        return view('system.settings', compact('productCfg', 'env'));
    }

    // ---------------------------------------------------------------- 内部方法

    private function columnsOf(string $table, array $exclude): array
    {
        if (! SystemConsole::hasTable($this->product, $table)) {
            return [];
        }

        return array_values(array_diff(Schema::connection($this->product)->getColumnListing($table), $exclude));
    }

    private function buildPrompt(array $fields): string
    {
        $label = $this->cfg['label'];
        $lines = [];
        $lines[] = "你是「{$label}」的系统管理员数据整理助手。";
        $lines[] = '请把我粘贴的表格内容转换成可直接导入系统的结构化数据，要求：';
        $lines[] = '1. 严格按下面的字段顺序输出，缺失值留空，不要臆造；';
        $lines[] = '2. 编码类字段保持唯一，重复的按 -01/-02 顺序编号；';
        $lines[] = '3. 输出为 CSV，首行为字段名，使用英文逗号分隔，不要加解释文字。';
        $lines[] = '';
        foreach ($fields as $entity => $cols) {
            if ($cols === []) {
                continue;
            }
            $lines[] = "【{$entity}】字段：" . implode(', ', $cols);
        }
        $lines[] = '';
        $lines[] = '示例输入：' . '张三 | 生产部 | 车间主任 | 13800000000';
        $lines[] = '示例输出：' . 'name,department,position,phone';

        return implode("\n", $lines);
    }

    private function insertOrg(string $table, array $data): void
    {
        $meta = SystemConsole::orgMeta($this->product, $table);
        $row = [];
        if ($meta['name']) {
            $row[$meta['name']] = $data['name'];
        }
        if ($meta['code'] && ! empty($data['code'])) {
            $row[$meta['code']] = $data['code'];
        }
        if ($meta['sort']) {
            $row[$meta['sort']] = (int) ($data['sort'] ?? 0);
        }
        if ($meta['department'] && ! empty($data['department_id'])) {
            $row[$meta['department']] = (int) $data['department_id'];
        }
        if ($meta['status']) {
            $sample = DB::connection($this->product)->table($table)->value($meta['status']);
            $row[$meta['status']] = is_numeric($sample) ? 1 : 'active';
        }
        foreach (['created_at', 'updated_at'] as $ts) {
            if (Schema::connection($this->product)->hasColumn($table, $ts)) {
                $row[$ts] = now();
            }
        }
        DB::connection($this->product)->table($table)->insert($row);
    }

    private function updateOrg(string $table, int $id, array $data): void
    {
        $meta = SystemConsole::orgMeta($this->product, $table);
        $row = [];
        if ($meta['sort'] && array_key_exists('sort', $data)) {
            $row[$meta['sort']] = (int) ($data['sort'] ?? 0);
        }
        if ($meta['status'] && array_key_exists('active', $data)) {
            $current = DB::connection($this->product)->table($table)->where('id', $id)->value($meta['status']);
            $row[$meta['status']] = is_numeric($current)
                ? (int) ((bool) $data['active'] ? 1 : 0)
                : ((bool) $data['active'] ? 'active' : 'disabled');
        }
        if (Schema::connection($this->product)->hasColumn($table, 'updated_at')) {
            $row['updated_at'] = now();
        }
        if ($row !== []) {
            DB::connection($this->product)->table($table)->where('id', $id)->update($row);
        }
    }

    private function human(int $bytes): string
    {
        if ($bytes <= 0) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 2) . ' ' . $units[min($i, 4)];
    }

    private function logTail(): array
    {
        $file = storage_path('logs/laravel.log');
        if (! is_file($file)) {
            return [];
        }
        $size = filesize($file);
        $fp = fopen($file, 'r');
        if (! $fp) {
            return [];
        }
        $offset = max(0, $size - 4096);
        fseek($fp, $offset);
        $chunk = fread($fp, 4096);
        fclose($fp);

        $lines = preg_split('/\r?\n/', (string) $chunk);
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));

        return array_slice($lines, -10);
    }
}
