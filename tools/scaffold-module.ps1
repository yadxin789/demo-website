# ============================================================================
#  生成一个产品模块的四件套
#  用法： pwsh tools\scaffold-module.ps1 -ProductCode erp -ProductClass Erp -Label "电子制造 ERP"
#         -Enabled $false 时生成预留位版本（Provider 直接 return，路由文件为空）
# ============================================================================
param(
    [Parameter(Mandatory = $true)][string]$ProductCode,
    [Parameter(Mandatory = $true)][string]$ProductClass,
    [Parameter(Mandatory = $true)][string]$Label,
    [bool]$Enabled = $true
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$base = Join-Path $root "app\Modules\$ProductClass"

New-Item -ItemType Directory -Force -Path (Join-Path $base 'Models') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $base 'Providers') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $base 'Routes') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $base 'Resources\views\layouts') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $base 'Resources\views\auth') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $base 'Resources\views\dashboard') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $base 'Resources\views\preview') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $base 'Resources\views\errors') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $base 'Http\Controllers') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $base 'Database\Migrations') | Out-Null
New-Item -ItemType Directory -Force -Path (Join-Path $base 'Database\Seeders') | Out-Null

# ---------------------------------------------------------------------------
# BaseModel
# ---------------------------------------------------------------------------
$baseModel = @'
<?php

declare(strict_types=1);

namespace App\Modules\__CLASS__\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 本产品所有模型的唯一基类。
 *
 * 硬约束（G-02）：模型不得直接 extends Model，必须 extends 本类。
 * 原因：$connection 必须由基类强制指定，否则会落到 database.default（= erp），
 *      出现"PM 的数据写进 ERP 库"这种最难查的事故。
 */
abstract class BaseModel extends Model
{
    protected $connection = '__CODE__';

    protected $guarded = [];

    protected $perPage = 15;
}
'@

# ---------------------------------------------------------------------------
# User
# ---------------------------------------------------------------------------
$userModel = @'
<?php

declare(strict_types=1);

namespace App\Modules\__CLASS__\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * __LABEL__ 的登录用户。
 *
 * 硬约束（HC-11）：五个产品各有自己的 users 表、User 模型、guard。
 * 五个模型类名相同但命名空间不同，互不引用，不做单点登录。
 */
class User extends Authenticatable
{
    use HasRoles;
    use Notifiable;

    protected $connection = '__CODE__';
    protected $table = 'users';

    protected $guarded = ['id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
'@

# ---------------------------------------------------------------------------
# Provider
# ---------------------------------------------------------------------------
if ($Enabled) {
    $provider = @'
<?php

declare(strict_types=1);

namespace App\Modules\__CLASS__\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * __LABEL__ 的模块装配器。
 *
 * 只做三件事：装路由、装视图命名空间、装迁移。
 * 不允许注册全局单例、不允许改其他产品的配置（HC-01）。
 */
class __CLASS__ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', '__CODE__');
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
    }
}
'@
}
else {
    $provider = @'
<?php

declare(strict_types=1);

namespace App\Modules\__CLASS__\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * __LABEL__ 预留位（enabled = false）。
 *
 * HC-29：本产品在正式开发前只允许存在"占位"，不装路由、不装视图、不装迁移。
 * 打开方式：把 config/products.php 里的 enabled 改 true，并替换本文件为完整版本。
 */
class __CLASS__ServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! config('products.products.__CODE__.enabled', false)) {
            return;
        }

        // 产品尚未开发（S5 / S6 阶段填充）
    }
}
'@
}

# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------
if ($Enabled) {
    $routes = @'
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| __LABEL__ 路由
|--------------------------------------------------------------------------
| HC-07：必须同时具备 prefix('__CODE__') 与 name('__CODE__.')
| HC-08：中间件链固定为 web -> module:__CODE__ -> product.only:__CODE__
|
| S0 只装占位路由验证"壳通了"；真实业务路由由 S1/S2/S3 从原项目搬入，
| 搬入时只加前缀、不改后缀。
|
*/

Route::middleware(['web', 'module:__CODE__', 'product.only:__CODE__'])
    ->prefix('__CODE__')
    ->name('__CODE__.')
    ->group(function () {

        Route::get('/', function () {
            return auth('__CODE__')->check()
                ? redirect()->route('__CODE__.dashboard')
                : redirect()->route('__CODE__.preview');
        })->name('root');

        Route::get('preview', fn () => view('__CODE__::preview.index'))->name('preview');

        Route::get('login', fn () => view('__CODE__::auth.login'))->name('login');

        Route::get('dashboard', fn () => view('__CODE__::dashboard.index'))->name('dashboard');

    });
'@
}
else {
    $routes = @'
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| __LABEL__ 路由（预留位，故意为空）
|--------------------------------------------------------------------------
| HC-29：产品未开发前，本文件不得出现任何 Route:: —— 加了就意味着有入口。
| S5 / S6 开发时，用 scaffold-module.ps1 -Enabled $true 重新生成为完整版本。
|
*/
'@
}

# ---------------------------------------------------------------------------
# 写入
# ---------------------------------------------------------------------------
$targets = @(
    @{ path = "Models\BaseModel.php";       tpl = $baseModel },
    @{ path = "Models\User.php";            tpl = $userModel },
    @{ path = "Providers\$($ProductClass)ServiceProvider.php"; tpl = $provider },
    @{ path = "Routes\web.php";             tpl = $routes }
)

foreach ($t in $targets) {
    $full = Join-Path $base $t.path
    $text = $t.tpl.Replace('__CODE__', $ProductCode).Replace('__CLASS__', $ProductClass).Replace('__LABEL__', $Label)
    Set-Content -LiteralPath $full -Value $text -Encoding utf8 -NoNewline
    Write-Output ("  wrote " + $t.path)
}

Write-Output ("[" + $ProductCode + "] scaffold done (enabled=" + $Enabled + ")")
