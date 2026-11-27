# demo-website 五产品独立演示站 · 任务清单 v3

> 替代 v2 清单。v3 变更：**取消 S3「切 MES」、S4「切 HR」**（ERP 不剥离）；原 S5 Shop 前移为 S3；新增 S5（MES 开发）、S6（HR 开发）、S7（五卡整合）。
> MES、HR 的细项见 `MES-任务清单.md`、`HR-任务清单.md`；本文件只列到"阶段衔接"粒度。
> 工时单位 `d`（半天 = 0.5d）。

---

## 第 0 部分｜总览

| 阶段 | 目标 | 任务数 | 工时 | 里程碑 |
|---|---|---|---|---|
| S-PRE | 环境与仓库 | 9 | 1.5d | `S0-pre` |
| S0 | 骨架：门户 3 卡 + 3 套壳 + MES/HR 预留位 | 28 | 4.5d | `S0-portal` |
| S1 | 搬 PM（裁 collab/voice/schedule） | 30 | 4d | `S1-pm` |
| S2 | 搬 ERP（**整块，一个不删**） | 33 | 7d | `S2-erp` |
| S3 | 搬 Shop | 31 | 5d | `S3-shop` |
| S4 | 三产品收尾与完工（内部验收，不对外） | 18 | 4d | **`S4-live3`（里程碑 A）** |
| S5 | MES 开发（衔接 5 项 + 详见 MES 清单） | 5 + 70 | 50d | `S5-mes` |
| S6 | HR 开发（衔接 5 项 + 详见 HR 清单） | 5 + 59 | 36d | `S6-hr` |
| S7 | 五卡整合与对外发布 | 14 | 4d | **`S7-live5`（里程碑 B）** |
| **合计** | | **176 项**（不含 MES/HR 细项） | **116d** | |

### 关键路径

```
S-PRE → S0 → S1(PM) → S2(ERP) → S3(Shop) → S4(上线三卡)
                                              ↘ S5(MES) → S6(HR) → S7(五卡)
```
S5 与 S6 可并行；S5 必须在 S2 之后（要参照 ERP 的模型与 UI 规范）。

---

## 第 1 部分｜S-PRE（9 项 / 1.5d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| S-PRE-01 | 初始化仓库 | `E:\demo-website` | `git init`；`.gitignore` 忽略 `vendor`/`node_modules`/`*.sqlite`/`storage/*` | `git status` 干净 | 0.5d |
| S-PRE-02 | 文档入库 | `docs\` | 本次 6 份文档全部入库 | 齐全 | 0.5d |
| S-PRE-03 | 环境确认 | — | PHP 8.3.x、Composer 2.x | 版本匹配 | 0.5d |
| S-PRE-04 | Laravel 12 骨架 | — | 仅框架壳，不取示例业务 | `artisan --version` = 12.x | 0.5d |
| S-PRE-05 | 依赖收敛 | `composer.json` | 以 `E:\erp-pm-soho\composer.json` 为基线；permission `8.3.2`、excel `^3.1`、guzzle `^7.10`、laravel/ai `1.2.3`、intervention `^4.3`、sanctum `^4.3` | `composer install` 成功 | 1d |
| S-PRE-06 | 目录骨架 | — | `app/Core`、`app/Modules/{Erp,Pm,Shop,Mes,Hr}`、`public/assets/{shared,portal,erp,pm,shop,mes,hr}`、`storage/*/{erp,pm,shop,mes,hr}` | 树形与 v3 §3 一致 | 0.5d |
| S-PRE-07 | 五个空库文件 | `database/*.sqlite` | erp/pm/shop/mes/hr 五个 | 均可写 | 0.5d |
| S-PRE-08 | 门禁脚本 | `tools/guard.ps1` | G-01~G-15 + 新增 G-16（预留位白名单）、G-17（enabled 一致性） | 可运行出报告 | 1d |
| S-PRE-09 | 首提交 | — | commit + tag `S0-pre` | tag 存在 | 0.5d |

---

## 第 2 部分｜S0 骨架（28 项 / 4.5d）

**目标**：门户 3 张卡片（ERP/PM/Shop）→ 各自预览页 → 各自登录页 → 各自空工作台；MES/HR 只留配置与目录，不出现任何入口。

### 2.1 配置层（8 项）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| S0-01 | 产品注册表（五条） | `config/products.php` | Erp/Pm/Shop 全字段齐备、`enabled=true`；**Mes/Hr 同结构记录、`enabled=false`**（`label`/`tagline` 先填，`description`/`features` 可留空待 S5/S6） | 五条记录结构一致 | 1d |
| S0-02 | 五连接 | `config/database.php` | 五 sqlite 连接；WAL、`busy_timeout=5000`、`foreign_key_constraints=true`、`synchronous=NORMAL` | 五连接均可 `getPdo()`（mes/hr 空库也通） | 0.5d |
| S0-03 | 五守卫 + 五 provider | `config/auth.php` | 五 `session` guard，各指 `App\Modules\{X}\Models\User` | 五 guard 可枚举 | 0.5d |
| S0-04 | 会话配置 | `config/session.php` | `driver=file`，路径按产品分支 | 五产品会话可并存 | 0.5d |
| S0-05 | 缓存配置 | `config/cache.php` | `default=file`，路径按产品分支 | 缓存不互串 | 0.5d |
| S0-06 | 队列配置 | `config/queue.php` | `default=sync` | 无 `jobs` 表可跑 | 0.5d |
| S0-07 | 日志五通道 | `config/logging.php` | 五 `daily` 通道，路径 `storage/logs/{x}` | 各写各的 | 0.5d |
| S0-08 | env 模板 | `.env` `.env.example` | `ERP_DB_*`…`HR_DB_*`；无裸 `DB_DATABASE` | 无裸键 | 0.5d |

### 2.2 Core 层（7 项）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| S0-09 | Product 读取器 | `Core/Support/Product.php` | 按 code / 按 URL 前缀 / 取 enabled 列表 / 排序 | 单测通过 | 0.5d |
| S0-10 | 装配器 | `Core/Support/ProductRegistry.php` | 按产品切默认连接、注册 guard | 切换正确 | 0.5d |
| S0-11 | 路径解析 | `Core/Support/ProductPaths.php` | session/cache/view/log/upload 五类 | 路径不重叠 | 0.5d |
| S0-12 | 上下文中间件 | `Core/Http/Middleware/SetProductContext.php` | 设当前产品、切连接、切日志 | 可读 `Product::current()` | 0.5d |
| S0-13 | 开关中间件 | `Core/Http/Middleware/EnsureProductEnabled.php` | `enabled=false` → 404（含 `/{x}/preview`，HC-30） | Mes/Hr 所有 URL 404 | 0.5d |
| S0-14 | 守卫选择中间件 | `Core/Http/Middleware/GuardByProduct.php` | 按前缀设默认 guard | `auth` 不依赖同名路由 | 0.5d |
| S0-15 | Core 提供者 | `Core/Providers/CoreServiceProvider.php` | 装门户路由、portal 视图命名空间、中间件别名、`redirectGuestsTo` 分流 | 全部生效 | 1d |

### 2.3 门户与预览（7 项）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| S0-16 | 门户控制器 | `Core/Http/Controllers/PortalController.php` | 只取 `enabled=true` 且按 `order` | 返回 3 条 | 0.5d |
| S0-17 | 门户视图 | `resources/views/portal/home.blade.php` | 三张卡（首期）；布局预留 3 列→可扩展到 5 列的网格 | 3 卡正常，第 4/5 格空置 | 1d |
| S0-18 | 门户样式 | `public/assets/portal/portal.css` | 卡片网格、主题色变量、响应式（手机单列） | 三档宽度正常 | 0.5d |
| S0-19 | 预览控制器 | `Core/Http/Controllers/PreviewController.php` | 按 `{product}` 渲染；不存在/disabled → 404 | 三产品可访，Mes/Hr 404 | 0.5d |
| S0-20 | 预览视图 | `resources/views/portal/preview.blade.php` | 品牌头、介绍、6–8 条功能、3–4 张截图位、演示账号、两个按钮 | 三产品内容各异 | 1d |
| S0-21 | 门户路由 | `routes/web.php` | `/`→`portal.home`；三条 `{x}.preview` 显式路由 | `route:list` 见 4 条 | 0.5d |
| S0-22 | 门户 404 | `resources/views/portal/errors/404.blade.php` | 带返回门户 | 正常 | 0.5d |

### 2.4 三套完整壳（每产品 2 项 ×3 = 6 项）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| S0-23~25 | Erp/Pm/Shop 四件套 | `Modules/{X}/` | `BaseModel`($connection)、`Providers/{X}ServiceProvider`（loadRoutes/loadViews/loadMigrations）、`Routes/web.php`（`web`+`module:{x}`+`product.only:{x}`+`prefix`+`name`）、`Models/User`、`Resources/views/{layouts,auth,dashboard}`、`assets/{x}/` | 六路由组可列 | 1.5d |
| S0-26~28 | 三套登录与工作台 | 同上 | `{x}.auth.login`、`{x}.dashboard`、`/{x}` 分流（HC-10） | 三产品可登录 | 1d |

### 2.5 视觉与账号（3 项）

| ID | 任务 | 明细 | 验收 | 工时 |
|---|---|---|---|---|
| S0-29 | 三套登录页 + 三套错误页 | 各自 logo/产品名/主题色；403/404/500 各一套 | 三套视觉明显不同 | 1d |
| S0-30 | 共用第三方资产 | bootstrap / bootstrap-icons / fontawesome / bundle.js 放 `assets/shared`（取三项目最完整一份） | 三产品引用正常 | 0.5d |
| S0-31 | 三产品种子账号 | 各 1–3 个，密码 `123456` | 三产品可登录 | 0.5d |

### 2.6 MES/HR 预留位（3 项）

| ID | 任务 | 明细 | 验收 | 工时 |
|---|---|---|---|---|
| S0-32 | Mes 预留位 | `BaseModel`($connection='mes')、`Models/User`、空 `Routes/web.php`、空 `Migrations/`、`MesServiceProvider`（enabled=false 直接 return）、`assets/mes/mes.css` 占位、logo/favicon 占位 | 无路由、无控制器、无视图（HC-29） | 0.5d |
| S0-33 | Hr 预留位 | 同构（connection='hr'） | 同上 | 0.5d |
| S0-34 | 预留位验证 | Mes/Hr 的 `/{x}`、`/{x}/preview`、`/{x}/login` 全部 404；门户仅 3 卡 | 全部符合 HC-30 | 0.5d |
| S0-35 | 门禁 + 打标 | G-01~G-17；tag `S0-portal` | 全绿 | 0.5d |

> 注：S0 实际为 35 行任务（其中 S0-23~28 合并计为 6 项），与总览"28 项"的差异来自合并计数，工时以本表为准。

---

## 第 3 部分｜S1 搬 PM（30 项 / 4d）

与 v2 完全一致，仅阶段编号不变。要点重申：

| 阶段块 | 任务 | 关键点 |
|---|---|---|
| 裁剪先行（3 项） | S1-01~03 | 删 collab/voice/wecom + 5 个 Command + `console.php` 清空 |
| 代码搬迁（18 项） | S1-04~21 | 31 控制器→28、42 模型全改 `extends BaseModel`、18 服务→16、21 视图目录、102 blade |
| 数据与验证（9 项） | S1-22~30 | 49 迁移→44、种子、权限、回归、门禁、tag `S1-pm` |

**验收**：`collab`/`voice` 0 命中；`Schedule::` 0 命中；项目→任务→工时→甘特→文档全链路可跑。

---

## 第 4 部分｜S2 搬 ERP（33 项 / 7d）

**v3 关键差异：不剥离、一个不删、不改任何业务逻辑。**

### 4.1 前置（3 项）

| ID | 任务 | 明细 | 验收 | 工时 |
|---|---|---|---|---|
| S2-01 | 路由名基线导出 | `route:list --json` 存基线；标出裸 `login`/`register`/`dashboard`/`home` | 基线文件生成 | 0.5d |
| S2-02 | 模块规模基线 | 记录 52 控制器 / 136 模型 / 84 迁移 / 171 blade / 47 视图目录 / 31 Command | 基线数字入文档 | 0.5d |
| S2-03 | `Purchase` vs `Purchasing` 比对 | 2 vs 5 个模型，判断是否重复 | **仅记录，不改**（HC-33 不改 ERP 功能） | 0.5d |

### 4.2 代码搬迁（22 项）

| ID | 任务 | 源 → 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| S2-04 | 控制器 52 | `app/Http/Controllers/*.php` → `Modules/Erp/Http/Controllers/` | 平铺全搬，**含 `HrController`/`HrAdvancedController`/`ProductionController`/`ApsController`/`IssueRequestController`** | 52 个，一个不少 | 1d |
| S2-05 | 控制器子命名空间 | 同上 | `Admin`/`System`/`App` 等 | 引用可解析 | 0.5d |
| S2-06 | 请求 22 | `app/Http/Requests/*` → `Modules/Erp/Http/Requests/` | 保留 10 个子目录 | 22 个 | 0.5d |
| S2-07 | 中间件 | `app/Http/Middleware/*` → `Modules/Erp/Http/Middleware/` | 含 `EnsureRoleScope` | 齐 | 0.5d |
| S2-08~15 | 模型 26 子域 | `app/Models/{子域}/*` → `Modules/Erp/Models/{子域}/` | **26 个子域全搬，含 `Hr`(7)、`Production`(13)、`Quality`(4)**；全部改 `extends BaseModel` | 136 个，无裸继承 | 3d |
| S2-16 | 服务 31 | `app/Services/*` → `Modules/Erp/Services/` | 含 `HrService`/`ApsSchedulingService`/`ProductionOrderService` | 31 个 | 1d |
| S2-17 | 其余分层 | `app/{Ai,Console,Enums,Policies,Support}` → 对应 | `Console/Commands` 11 个按 §7.2（留类、删调度） | 无缺 | 1d |
| S2-18 | 命名空间替换 | 全部 Erp 文件（307 个 php） | `App\Http\`→`App\Modules\Erp\Http\`、`App\Models\{子域}\`→`App\Modules\Erp\Models\{子域}\` 等 | `php -l` 全通过 | 1.5d |
| S2-19 | 引用点修正（8 类） | 路由/Blade/config/工厂/测试 | §6.3 的 8 类；注意 `Models\Hr`/`Models\Production` 的跨子域引用 | `dump-autoload` 无警告 | 1d |
| S2-20 | 路由全量搬（900 行） | `routes/web.php` → `Modules/Erp/Routes/web.php` | 加 `prefix('erp')`+`name('erp.')`；**保留全部** `production.*`/`quality.*`/`hr.*`/`hra.*` | 条数 = 基线 | 1d |
| S2-21 | 路由名前缀化 | 同上 | 全部 `->name('x')`→`->name('erp.x')`；裸 `login`→`erp.auth.login` 等 | G-06 绿 | 1d |
| S2-22 | 视图 47 目录 / 171 blade | `resources/views/*` → `Modules/Erp/Resources/views/` | **含 `production`/`quality`/`hr`/`hr-adv` 目录** | 目录数 = 47 | 1d |
| S2-23 | 视图引用改名 | 全部 blade | `erp::` | G-03 绿 | 1d |
| S2-24 | 语言包 | `lang/*` → `Modules/Erp/Resources/lang/` | 原样 | 齐 | 0.5d |
| S2-25 | 产品配置 | `config/{erp,…}.php` → `Modules/Erp/Config/` | 不覆盖 `app.name` | 无全局覆盖 | 0.5d |

### 4.3 资产与 UI（5 项）

| ID | 任务 | 明细 | 验收 | 工时 |
|---|---|---|---|---|
| S2-26 | 原布局保留 | `layouts/admin.blade.php` 等原样 | 后台渲染正常 | 1d |
| S2-27 | 主题 css | `erp.css`+`all.min.css`+`bootstrap-icons` → `assets/erp`（bootstrap 归 shared） | 无 404 | 0.5d |
| S2-28 | 登录页改造 | `erp.auth.login` 换 ERP 品牌 | 能登录 | 0.5d |
| S2-29 | `/erp` 分流 | 未登录→preview，已登录→dashboard | 正确 | 0.5d |
| S2-30 | 资产清点 | `public/{css,js,fonts,webfonts}` 全量迁移 | 全站无 404 | 1d |

### 4.4 数据与验证（6 项）

| ID | 任务 | 明细 | 验收 | 工时 |
|---|---|---|---|---|
| S2-31 | 迁移 84 全量 | → `Modules/Erp/Database/Migrations/`，**文件名不改、表名不改、一个不拆** | 84 个 | 1d |
| S2-32 | permission 6.23→8.3.2 适配 | 中间件类名、`HasRoles` 接口、`Role` 表结构 | 权限校验通过 | 1d |
| S2-33 | 建库与迁移 | `--database=erp --path=… --force` | 84 全绿，含 `hr_*`/`production_*`/`aps_*`/`quality_*` | 1d |
| S2-34 | 种子 | `AdminSeeder`/`DemoUserSeeder`/`PermissionSeeder`/`RoleSeeder`/`DemoDataSeeder`/`ApsSeeder`/`DepartmentSeeder`/`PositionSeeder` 等 | 演示账号登录成功 | 1d |
| S2-35 | 核心链路回归 | 主数据→销售→采购→库存→BOM→财务 + **生产工单→报工→质检** + **HR 页面可访问** | 全链路可跑（含生产与人事） | 1.5d |
| S2-36 | 门禁 + 打标 | G-01~G-17；tag `S2-erp` | 全绿；**ERP 路由条数 = 基线（HC-33）** | 0.5d |

---

## 第 5 部分｜S3 搬 Shop（31 项 / 5d）

与 v2 的 S5 完全一致（原编号 S5-01~31 → 现 S3-01~31）。要点：

| 块 | 任务 | 关键点 |
|---|---|---|
| 前置（2） | S3-01~02 | 11 个控制器子目录；`config('shop.assets.*')` 路径改造 |
| 代码搬迁（19） | S3-03~21 | 50 控制器、71 模型、17 请求、55 服务、117 blade、18 视图目录、`web.php`+`api.php` |
| 数据与验证（10） | S3-22~31 | 69 迁移；商品/门店/会员/库存/日结种子；收银链路回归；tag `S3-shop` |

**验收**：收银→支付（模拟）→日结全链路可跑；8 个演示账号（密码 123456）各角色可登录。

---

## 第 6 部分｜S4 三产品收尾与上线（18 项 / 4d）

### 6.1 门户与预览成品化（6 项）

| ID | 任务 | 明细 | 验收 | 工时 |
|---|---|---|---|---|
| S4-01 | 三张卡片成品化 | logo、tagline、主题色、hover | 达"产品目录"水准 | 1d |
| S4-02 | 三份产品介绍文案 | 面向谁/解决什么/典型场景 | 三份定位不重叠 | 0.5d |
| S4-03 | 三份功能清单 | 各 6–8 条，与真实功能对应 | 无夸大 | 0.5d |
| S4-04 | 三套界面截图 | 各 3–4 张放 `assets/{x}/shots/` | 清晰无脏数据 | 1d |
| S4-05 | 演示账号展示 | 账号/123456/角色说明 | 三产品齐 | 0.5d |
| S4-06 | 预览页响应式 | 手机/平板/桌面 | 无错位 | 0.5d |

### 6.2 视觉穿帮专项（4 项）

| ID | 任务 | 明细 | 验收 | 工时 |
|---|---|---|---|---|
| S4-07 | 三套 layout 独立性 | 无跨产品引用 | 0 引用 | 0.5d |
| S4-08 | 三套错误页核查 | 403/404/500 各带品牌 | 三套各 3 页 | 0.5d |
| S4-09 | 三套登录页排查 | 无残留"Powered by"、无共用页脚 | 干净 | 0.5d |
| S4-10 | 交叉走查 | 客户视角连点三卡 | 无"同一系统"感 | 1d |

### 6.3 部署上线（8 项）

| ID | 任务 | 明细 | 验收 | 工时 |
|---|---|---|---|---|
| S4-11 | 生产 `.env` | 五套前缀、`APP_ENV=production`、`APP_DEBUG=false`、`MAIL_MAILER=log` | 无敏感泄露 | 0.5d |
| S4-12 | Nginx | 单 vhost、静态过期、拒绝 `.env`/`*.sqlite` | `curl` 验证 403 | 0.5d |
| S4-13 | PHP-FPM | `pm=static`、`max_children=4`、`memory_limit=256M` | 10 并发正常 | 0.5d |
| S4-14 | OPcache | 开启、上线后 `validate_timestamps=0` | 首屏变快 | 0.5d |
| S4-15 | 缓存预热 | `config:cache`+`route:cache`+`view:cache` | 三产品正常 | 0.5d |
| S4-16 | 备份脚本 | 三库 `wal_checkpoint` + storage | 可还原 | 0.5d |
| S4-17 | 一键部署 | `deploy/deploy.sh` | 空机一次跑通 | 1d |
| S4-18 | 里程碑 A 验收 | 三卡全走查 + 门禁 | **tag `S4-live3`** | 0.5d |

---

## 第 7 部分｜S5 MES 开发（衔接 5 项 + 70 项明细）

**详见 `MES-任务清单.md`（M0~M6 共 70 项 / 50d，含 Excel 导入导出 4 项）。** 本文件只列与主项目的衔接任务：

| ID | 任务 | 明细 | 验收 | 工时 | 状态 |
|---|---|---|---|---|---|
| S5-01 | 预留位激活 | `products.php` 的 `mes` 记录填全（description/features/shots/demo_account）；`enabled` 先保持 false | 记录完整 | 0.5d | ✅ `94a19de` |
| S5-02 | 打开路由注册 | `MesServiceProvider` 去掉 enabled 短路（或改为读配置） | `mes.*` 路由可列 | 0.5d | ✅ `94a19de` |
| S5-03 | 补登录与工作台 | 按 MES 规划书的设计补（不算"空白入口"，此时已有业务页面） | 能登录进看板 | 0.5d | ✅ `f16e044` |
| S5-04 | 三卡回归 | 确认新增 MES 未影响 ERP/PM/Shop（HC-34） | 三卡全链路正常 | 0.5d | ✅ 门禁 G-01~G-17 + `check-isolation.php` PASS 20/0 |
| S5-05 | 点亮卡片 | `mes.enabled=true`，门户出现第 4 张卡 | 卡片可点进预览页 | 0.5d | ✅ `c506d8f` 之后已点亮 |

---

## 第 8 部分｜S6 HR 开发（衔接 5 项 + 59 项明细）

**详见 `HR-任务清单.md`（H0~H5 共 59 项 / 36d，含 Excel 导入导出 3 项）。** 衔接任务同 S5-01~05，把 `mes` 换成 `hr`。

| ID | 任务 | 明细 | 验收 | 工时 | 状态 |
|---|---|---|---|---|---|
| S6-01 | 预留位激活 | `HrServiceProvider` 注册路由/视图/迁移；`products.php` 的 `hr` 记录填全 | `hr.*` 路由可列 | 0.5d | ✅ `b869c29`（64 条 `hr.*`） |
| S6-02 | 补登录与工作台 | 紫灰主题登录页 + 人力看板（5 KPI / 部门条 / 异常 / 待审请假 / 合同到期 / 本月工资 / 招聘） | 能登录进看板 | 0.5d | ✅ `b869c29` |
| S6-03 | 组织与员工落地 | 部门树 / 岗位 / 编制 + 员工列表 / 详情 / 增改 + 合同 / 异动 | 六页可读写 | 0.5d | ✅ 本轮（`H1-01`~`H1-12`） |
| S6-04 | 五卡回归 | 确认 HR 未影响 ERP/PM/Shop/MES（HC-34） | 五卡全链路正常 | 0.5d | ✅ 门禁 PASS 17/0；独立性 PASS 31/0（已含 HR） |
| S6-05 | 点亮卡片 | `hr.enabled=true`，门户出现第 5 张卡 | 卡片可点进预览页 | 0.5d | ✅ 已点亮（紫灰主题） |

---

## 第 9 部分｜S7 五卡整合收尾（14 项 / 4d）

| ID | 任务 | 明细 | 验收 | 工时 |
|---|---|---|---|---|
| S7-01 | 门户五卡布局 | 3 列 → 5 卡自适应（大屏 5 列、中屏 3 列、手机 1 列） | 五卡排布美观 | 0.5d |
| S7-02 | MES 卡片成品化 | logo/tagline/主题色/截图 | 达三卡水准 | 0.5d |
| S7-03 | HR 卡片成品化 | 同上 | 同上 | 0.5d |
| S7-04 | MES/HR 预览页内容 | description/features/shots/demo_account | 齐备 | 0.5d |
| S7-05 | 五人设一致性核查 | 五家公司的公司名/人名/编码规则/单号前缀互不重复（HC-32） | 抽查无串 | 0.5d |
| S7-06 | 销售路径落地 | §1.2 分流话术写进预览页"适合谁"栏 + 演示手册 | 五个产品各有"适合谁" | 0.5d |
| S7-07 | 五账号交叉验证 | 每产品账号去其他产品登录必须失败 | 全部拒绝 | 0.5d |
| S7-08 | 五套视觉排查 | layout/错误页/登录页/favicon 各 5 套 | 无共用 | 0.5d |
| S7-09 | 客户视角全走查 | 五卡→五预览→五登录→五工作台→各一条主链路 | 无"同一系统"感 | 1d |
| S7-10 | 冷启动演练 | 删五库→跑全部种子→五产品可用 | 一次成功 | 0.5d |
| S7-11 | 备份与部署更新 | 五库备份脚本、部署脚本纳入两新产品 | 可跑 | 0.5d |
| S7-12 | 门户线索捕获 | 页脚统一联系方式 + "预约演示"表单 → 新增 `database/portal.sqlite` 只存 `leads` 表（不读任何产品库，HC-14 不受影响） | 提交可存、后台可查 | 0.5d |
| S7-13 | 合规信息页 | 页脚备案号、隐私说明、公司/个人信息 | 国内合规 | 0.5d |
| S7-14 | 里程碑 B 验收 | 全量门禁 + tag `S7-live5` | 全绿 | 0.5d |

---

## 第 10 部分｜验收清单

### 里程碑 A（`S4-live3`）
- [ ] 门户仅 3 卡；MES/HR 的 `/{x}`、`/{x}/preview`、`/{x}/login` 全 404
- [ ] 三产品独立登录、独立库、独立视觉、独立演示数据
- [ ] PM 无 collab/voice；全局无定时任务
- [ ] ERP 路由条数 = 原始基线（HC-33）
- [ ] G-01~G-17 全绿
- [ ] 单 vhost 跑起三产品

### 里程碑 B（`S7-live5`）
- [ ] 门户 5 卡，五产品独立登录/库/视觉/演示数据
- [ ] MES 能跑通"工单→派工→报工→质检"（见 MES 规划书 §7）
- [ ] HR 能跑通"入职→考勤→请假→薪资→绩效"（见 HR 规划书 §7）
- [ ] 五账号交叉登录全部被拒
- [ ] 五产品公司人设不串（HC-32）
- [ ] 三组重叠话术齐备（§1.3）
- [ ] 客户走查无"同一系统"感
