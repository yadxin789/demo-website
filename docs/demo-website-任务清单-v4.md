# demo-website 五产品独立演示站 · 任务清单 v4

> v4 在 v3 清单（S-PRE ~ S7 已完成）之上新增 **S8 ~ S13**：门户文案与五产品门面、登录统一"邮箱或用户名"、布局统一（PM 风格共享壳）、ERP/Shop 对账、MES/HR 后台完备、回归上线。v3 已完成部分保持原样。
> 细项仍见 `MES-任务清单.md`、`HR-任务清单.md`；本文件列到可勾选粒度。工时单位 `d`（半天 = 0.5d）。

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
| S6 | HR 开发（衔接 5 项 + 详见 HR 清单） | 5 + 59 | 36d | `S6-hr` ✅ 全部完成 |
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
| S6-04 | 五卡回归 | 确认 HR 未影响 ERP/PM/Shop/MES（HC-34） | 五卡全链路正常 | 0.5d | ✅ 门禁 PASS 15/0+SKIP 2；独立性 PASS 31/0；四产品 404/preview/login 回归全通过 |
| S6-05 | 点亮卡片 | `hr.enabled=true`，门户出现第 5 张卡 | 卡片可点进预览页 | 0.5d | ✅ 已点亮（紫灰主题） |

**S6 收尾实况（H5 轮）**：`hr.*` 路由 102 条全部接真控制器（占位控制器与占位视图已删）；门禁 G-01~G-17 PASS；独立性 PASS 31/0；`ReportController` 三报表、`SystemController` 三系统页、`ImportController` 导入导出全链路实测通过；403 品牌页经 `bootstrap/app.php` 补 `HttpException(403)` 匹配后生效（此前 `abort(403)` 落 Laravel 默认英文页）。

---

## 第 9 部分｜S7 五卡整合收尾（14 项 / 4d）

| ID | 任务 | 明细 | 验收 | 工时 |
|---|---|---|---|---|
| S7-01 | 门户五卡布局 | 3 列 → 5 卡自适应（大屏 5 列、中屏 3 列、手机 1 列） | 五卡排布美观 | ✅ 0.5d |
| S7-02 | MES 卡片成品化 | logo/tagline/主题色/截图 | 达三卡水准 | ✅ 0.5d |
| S7-03 | HR 卡片成品化 | 同上 | 同上 | ✅ 0.5d |
| S7-04 | MES/HR 预览页内容 | description/features/shots/demo_account | 齐备 | ✅ 0.5d |
| S7-05 | 五人设一致性核查 | 五家公司的公司名/人名/编码规则/单号前缀互不重复（HC-32） | 抽查无串 | ✅ 0.5d |
| S7-06 | 销售路径落地 | §1.2 分流话术写进预览页"适合谁"栏 + 演示手册 | 五个产品各有"适合谁" | ✅ 0.5d |
| S7-07 | 五账号交叉验证 | 每产品账号去其他产品登录必须失败 | 全部拒绝 | ✅ 0.5d |
| S7-08 | 五套视觉排查 | layout/错误页/登录页/favicon 各 5 套 | 无共用 | ✅ 0.5d |
| S7-09 | 客户视角全走查 | 五卡→五预览→五登录→五工作台→各一条主链路 | 无"同一系统"感 | ✅ 1d |
| S7-10 | 冷启动演练 | 删五库→跑全部种子→五产品可用 | 一次成功 | ✅ 0.5d |
| S7-11 | 备份与部署更新 | 五库备份脚本、部署脚本纳入两新产品 | 可跑 | ✅ 0.5d |
| S7-12 | 门户线索捕获 | 页脚统一联系方式 + "预约演示"表单 → 新增 `database/portal.sqlite` 只存 `leads` 表（不读任何产品库，HC-14 不受影响） | 提交可存、后台可查 | ✅ 0.5d |
| S7-13 | 合规信息页 | 页脚备案号、隐私说明、公司/个人信息 | 国内合规 | ✅ 0.5d |
| S7-14 | 里程碑 B 验收 | 全量门禁 + tag `S7-live5` | 全绿 | ✅ 0.5d |

**S7 收尾实况（S7-10 轮）**

- **冷启动演练一次通过**（`tools/coldstart.ps1`，支持 `-Restore` 回滚 / `-Keep` 删快照）：六库整体挪到 `database/_coldstart_20261127` → 建空库 → 逐库迁移（erp 85 / pm 40 / shop 70 / mes 8 / hr 9 / portal 2 批次）→ 逐库种子（五产品 DatabaseSeeder + Shop 补灌 DemoDataSeeder/AiSeeder）→ 全绿。演练后数据与演练前一致：shop 商品 24、hr 员工 80、pm 用户 58、mes 工单 20、erp 用户 30、erp 表 294。
- **冷启动暴露并修复一个真实缺陷**：做完库重建后 ERP 权限页全部 403。根因是 spatie 的权限缓存全局只有一份、不区分数据库——门户首页请求先在默认库把缓存建起来，ERP 请求复用这份快照就判定"没有 bom.view 等权限"。修法：`SetProductContext` 每次切库后 `forgetCachedPermissions()`，让缓存按本产品库重建；`coldstart.ps1` 与 `deploy/deploy.sh` 另加了 `permission:cache-reset` 兜底。修复后"先访问门户、再登录 ERP"访问 `/erp/bom`、`/erp/bom/import`、`/erp/bom/pending`、`/erp/crm/follow-ups` 全部 200。
- **验收脚本两处加固**：`check-walkthrough.ps1` 因 ERP/Shop 登录带 5 次/分钟限流，演练前先 `cache:clear` 清桶（否则连续跑会把 429 误报成登录失败）；`check-http.ps1` 从三产品扩到五产品 + 门户（新增 `/about`、五卡计数、MES/HR 登录与抽样、五个品牌 404），并对 404 步骤改用 curl、补了子进程清理（`artisan serve` 会再起一个 `php -S`，只杀父进程会挂住输出管道）。
- 演练后回归：走查 17/0、交叉登录 5/0、人设 10/0、独立性 31/0、视觉 45/0、门禁 PASS 15/0+SKIP 2。

---

## 第 9.1 部分｜上线实况（2026-11-27，已完成）

| 项 | 结果 |
|---|---|
| 服务器 | `123.207.233.94`（Ubuntu 22.04.5，PHP 8.3.37，nginx + php8.3-fpm） |
| 缺失补装 | 服务器原无 `pdo_sqlite`，已装 `php8.3-sqlite3` 并 reload FPM；否则所有迁移报 `could not find driver` |
| 站点目录 | `/var/www/demo-website`（属主 `yadxin:www-data`），旧站 `/var/www/erp-shop` 及其 MySQL 库 `erp_shop` 已按要求**删除不保留** |
| 六库 | erp 294 表 / pm 49 / shop 83 / mes 33 / hr 34 / portal 2，Shop 演示数据 24 商品 · 61 POS 订单 · 18 销售订单 |
| 路由 | 948 条，`route:cache` 已生成 |
| nginx | 新站点 `demo-website`（80 域名 301 → HTTPS、80 IP 兜底、443 SSL 复用 `yad-erp.com` 证书）；`sites-enabled` 只剩它 |
| 公网验收 | `http://123.207.233.94/` 首页 5 卡；五 preview 200；五 login → 五看板全 200（erp 8.2KB / pm 24KB / shop 61KB / mes 17KB / hr 21KB）；六个 404 品牌全命中；交叉登录均被拒 |
| 踩坑（已修进脚本） | `deploy.sh` 原把 `database/` 设成 750、库文件 640，FPM（www-data）只读 → 登录报 `attempt to write a readonly database`。已改 770 + 660 + `g+s`，并提交 `c25ca2d` |
| 待办 | 域名 HTTPS 目前握手被拦（证书 CN/SAN 正常、IP 443 可达），属未备案域名 443 受限；备案下来后再验 `https://yad-erp.com`。备案号未批复期间页脚显示"ICP 备案办理中"，批复后只改 `.env` 的 `PORTAL_ICP` 即生效 |
| 门户对外信息 | 邮箱 `yadxin789@163.com`、微信 `yadxin789`（已上线）；**不提供电话**（`PORTAL_CONTACT_PHONE` 留空，模板按空值自动不渲染，避免骚扰） |
| 线索通知 | 不做"新线索发邮件"通知（用户 2026-11-27 明确暂不需要）；线索仍落 `portal.sqlite.leads`，等 ICP 备案批复后再决定是否加通知 |

---

## 第 9.2 部分｜S8 门户与五产品门面（14 项）

> 目标：卡片不再直落登录页；五个产品各有"对外首页"，免登录可看，看完再决定登录。
> 完成标记：`S8-facade`。

- [ ] S8-01 门户 `home.blade.php` 删除"一个站点，5 套独立系统"整段（含其后两句说明）
- [ ] S8-02 门户卡片链接由 `/{p}/login` 改为 `/{p}/`，卡片文案保持"产品名 + tagline + 适用对象"
- [ ] S8-03 五产品路由新增 `GET /{p}/` → `HomeController@landing`（ERP 复用 `ErpLandingController`，避免与工作台 `home` 冲突）
- [ ] S8-04 已登录访问 `/{p}/` 时 302 到各自工作台（ERP `/erp/home`，其余 `/{p}/dashboard`）
- [ ] S8-05 首页视图不含任何 DB 查询（只读 `config/products.php`、`config/portal.php`）
- [ ] S8-06 PM 首页：按参考 `welcome.blade.php` 区块顺序重写，文案改"研发项目管理"、公司"深圳智软科技"
- [ ] S8-07 ERP 首页：同结构，文案改"电子制造 ERP"、公司"东莞恒创电子"（参考项目 `/` 是工作台，故按规则复制 PM 首页）
- [ ] S8-08 Shop 首页：按 `E:\erp-shop\resources\views\landing.blade.php` 移植，文案改"百货零售 ERP"、公司"悦家连锁百货"，补 POS 能力清单
- [ ] S8-09 MES 首页：同结构，文案改"智能制造 MES"、公司"精工精密制造（苏州）"
- [ ] S8-10 HR 首页：同结构，文案改"人事管理 HR"、公司"华瑞集团（控股）"
- [ ] S8-11 五首页统一放"演示账号卡"：账号 + 密码 `123456` + `进入演示登录` 按钮
- [ ] S8-12 五首页页脚：`← 返回产品列表` + 邮箱/微信（读 `config/portal.php`，电话不显示）
- [ ] S8-13 门户 `/about` 与五首页页脚文案口径一致（备案"办理中"）
- [ ] S8-14 验收：五个 `/{p}/` 200 且首屏无 DB 查询；点击五卡落地到首页而非登录页；已登录 302 正确

---

## 第 9.3 部分｜S9 登录统一"邮箱或用户名"（10 项）

> 完成标记：`S9-login`。

- [x] S9-01 五登录页 label 统一「邮箱或用户名」+ placeholder 示范两种写法（**决策变更**：输入框 `name` 保留 `account`/`username` 不动，避免波及 200+ 存量视图与旧表单；后端统一判定"含 @ 走 email，否则 username"）
- [x] S9-02 ERP `AuthController::login` 原本即支持 username/email，未改动
- [x] S9-03 Shop 登录原本即支持 username/email，未改动
- [x] S9-04 PM `AuthController::login` 改判定：`FILTER_VALIDATE_EMAIL` → email，否则 username；限流键用提交值；失败文案统一「账号或密码错误」
- [x] S9-05 MES `LoginController` 由 email/name 改为 email/username
- [x] S9-06 HR `LoginController` 由 email/name 改为 email/username
- [x] S9-07 PM 迁移 `2026_11_27_100001_add_email_to_users_table`（nullable+unique，带 hasColumn 保护）+ 58 条按 `{username}@pm.demo` 回填
- [x] S9-08 MES 迁移 `2026_11_27_100002_add_username_to_users_table` + Seeder 回填 `admin`
- [x] S9-09 HR 迁移 `2026_11_27_100003_add_username_to_users_table` + Seeder 回填 `admin`
- [x] S9-10 ERP/Shop Seeder 补 email（`admin@erp.demo` / `admin@shop.demo`）；验收脚本 10/10：五产品 `admin` 与 `admin@{x}.demo` 两种写法均 302 → 看板 200

---

## 第 9.4 部分｜S10 布局统一（共享皮肤，5 项）

> 完成标记：`S10-shell`。基准：`E:\my-pm\pm-server-openEuler` 布局。
>
> **范围调整（已实施）**：不改写共享壳模板、不动侧栏几何宽度。
> 理由：五产品共 200+ 业务视图是按各自侧栏宽度手写的，重写骨架会把表格/看板挤变形；
> 真正决定"看起来是不是同一套系统"的是色彩、间距、圆角、激活态这些皮肤层，因此只做皮肤统一。

- [x] S10-01 新增 `public/assets/shared/css/shell.css`（CSS 变量 + 五套选择器映射；侧栏渐变 `#1a1a2e→#16213e`、激活 `inset 3px 0 0 var(--brand)`、顶栏白底 56px、内容区 22/24 padding、卡片 10px 圆角、表头 `#f8fafc`、按钮 8px 圆角）
- [x] S10-02 五个 layout 在 `</head>` 前注入 `--brand/--brand-dark`（取自 `config('products.products.X.theme')`）+ 引入 `shell.css`（放在各自内联 CSS 之后，保证覆盖）
- [x] S10-03 侧栏 / 顶栏 / 内容区 / 卡片 / 表格 / 按钮 / 表单 focus 七类元素统一（ERP `.sidebar/.topbar`、PM `.sidebar`、Shop `.app-sidebar/.app-topbar`、MES `.mes-side/.mes-topbar`、HR `.hr-side/.hr-topbar`）
- [x] S10-04 POS 全屏收银布局不受影响（`shell.css` 不含 POS 选择器）
- [x] S10-05 验收：`pwsh tools\check-visual.ps1` **45/0**；五看板 200 且品牌变量分别为 erp `#0d6efd` / pm `#198754` / shop `#fd7e14` / mes `#6f42c1` / hr `#5f3dc4`

---

## 第 9.5 部分｜S11 ERP / Shop 对账（6 项）

> 完成标记：`S11-parity`。脚本 `php tools/check-parity.php erp|shop|pm`。

- [x] S11-01 新增 `tools/check-parity.php`：抓 `->name('x')` 归一化后比路由名集合、比视图 `.blade.php` 集合，输出差异 + 白名单理由，退出码 0/1
- [x] S11-02 ERP 对账（基线 `E:\ddwl-erp\erp`）：路由 353/355 **缺 0**，视图 171/174 **缺 0** → 通过
- [x] S11-03 Shop 对账（基线 `E:\erp-shop`）：视图 117/117 **缺 0**；路由差异 14 项全部登记白名单（来源为参考项目 `routes/api.php` 的 AI 外部数据 API，需 `X-AI-Key` + 委托令牌，演示站不开放）→ 通过
- [x] S11-04 ERP 与参考的功能层一致性已由 S11-02 覆盖；参考项目 `/` 是登录后工作台（`home.blade.php`），演示站 `/` 是产品首页（S8 交付），属入口形态差异，不是功能缺失
- [x] S11-05 Shop 缺项判定：14 项均为 AI 外部 API，**不补**（客户拿不到 key，开放只增加攻击面），已写入白名单
- [x] S11-06 验收：`check-parity.php erp` / `shop` 退出码 0；`pm` 仅报告（差异 83 项 = 协同办公/电话会议/wecom 等按要求裁剪的功能），不参与门禁

---

## 第 9.6 部分｜S12 MES / HR 后台完备（MES 12 项 + HR 12 项 + 公共 4 项）

> 完成标记：`S12-admin`。原则：只动本产品自己的库，不碰 ERP / 其它产品（HC-11）。

### MES（12 项）
- [x] S12-M01 迁移 `2026_11_27_100004_create_mes_org_tables`：建 `departments`（name/parent_id/sort/status）、`positions`（name/department_id/status），`users` 加 `department_id`、`position_id`
- [x] S12-M02 种子 `OrgSeeder`：7 个部门（生产中心 / 品质部 / 设备部 / 计划部 / 信息部 + 一号车间 A 班 / SMT 车间）、5 个岗位、5 个演示账号挂靠（幂等可重跑）
- [x] S12-M03 部门管理 `system/departments`：列表（上级/账号数/岗位数）+ 新增 + 行内编辑（名称/上级/排序/启停）+ 删除（有子部门或有账号时拒绝）
- [x] S12-M04 岗位管理 `system/positions`：列表（所属部门/在岗人数）+ 新增 + 行内编辑 + 删除（有账号占用时拒绝）
- [x] S12-M05 用户管理 `system/users`：列表（搜索姓名/账号/邮箱/工号 + 状态筛选）+ 新增 + 编辑 + 启停 + 改角色 + 删除；**密码由操作者填写，页面不预填**（守 G-10）
- [x] S12-M06 角色权限：沿用现有 `system/roles` 权限矩阵页（按 guard `mes` 隔离），未重建
- [x] S12-M07 权限校验：用户/部门/岗位/角色四页统一 `hasRole('admin')`，非管理员 403（判定放在方法内，避免 `route:list` 被构造器拦）
- [ ] S12-M08 审计：**本版不做**（演示站不落操作审计表，理由记录在规划书 12.7）
- [x] S12-M09 菜单：侧栏「系统管理」分组 = 部门管理 / 岗位管理 / 账号管理 / 角色权限 / 站内通知，非管理员整组隐藏
- [x] S12-M10 演示动线实测：新建部门 → 新建账号（挂部门岗位）→ 改角色 → 停用 → 删除，列表与 KPI 实时变化
- [x] S12-M11 与 ERP 的 hr 预留位不冲突：只写 `mes.sqlite`，`check-isolation.php` 通过
- [x] S12-M12 验收：部门/岗位/账号三类 CRUD 全通；新账号可用 `admin` 或邮箱两种方式登录（实测 dashboard 200）

### HR（12 项）
- [x] S12-H01 迁移：`users.username` 已在 S9 补（`2026_11_27_100003`）；`departments`/`positions` 原本已有 `sort`/`status` 字段，无需再迁移
- [x] S12-H02 种子：部门树 / 岗位 / 5 个演示账号（沿用现有 seeder，未改数据口径）
- [x] S12-H03 部门管理 `org/departments`：现有树形列表 + 新增 + 编辑 + 删除（有下级或在职员工时拒绝），本版补齐校验说明
- [x] S12-H04 岗位管理 `org/positions`：现有新增/编辑 + **本版新增删除**（在职员工占用时拒绝）+ 行内删除按钮
- [x] S12-H05 用户管理 `system/users`：列表（搜姓名/用户名/邮箱/工号）+ **本版新增** 新建 / 编辑 / 删除，保留原有改角色、启停；密码由操作者填写
- [x] S12-H06 角色权限：沿用现有 `system/roles` 权限矩阵（按 guard `hr` 隔离）+ `role_code` 同步
- [x] S12-H07 权限校验：四页统一 `hasRole('admin')`，非管理员 403
- [ ] S12-H08 审计：**本版不做**（同 M08）
- [x] S12-H09 菜单：侧栏「系统管理」分组 = 部门管理 / 岗位管理 / 账号管理 / 角色权限 / 站内通知（原先分散在「组织与编制」组，已收拢）
- [x] S12-H10 演示动线实测：新建账号（角色 hr_specialist）→ 改角色 → 停用 → 删除，列表实时变化
- [x] S12-H11 员工档案（`employees`，业务数据）与登录账号（`users`，账号）两表分离，本版未混用
- [x] S12-H12 验收：账号 CRUD 全通（新建 → 邮箱登录 dashboard 200 → 编辑页 200 → 删除后列表无残留）；岗位删除按钮在位

### 公共（4 项）
- [ ] S12-P01 操作日志表迁移与模型：**本版不做**（与 M08/H08 一致）
- [ ] S12-P02 冷启动脚本覆盖三个新迁移（pm/mes/hr）→ 归入 S13-05
- [ ] S12-P03 `tools/check-isolation.php` 增 MES/HR 跨库读写两条 → 归入 S13-03
- [ ] S12-P04 `tools/guard.ps1` 增 HC-36~43 专项 → 归入 S13-01

---

## 第 9.7 部分｜S13 回归与上线（8 项）

- [x] S13-01 门禁 `pwsh tools\guard.ps1`：**PASS 17 / FAIL 0**（曾因新迁移缺 `$connection`、新模型裸 `extends Model` 失败两项，已修）
- [x] S13-02 走查 `check-walkthrough.ps1 -Base http://127.0.0.1:8201`：**17/0**（口径已改为"卡片直连产品首页"，不再断言 preview）
- [x] S13-03 交叉登录 **5/0**（HR 外来账号改用 `admin@erp.demo`）、人设 **10/0**、独立性 **31/0**、视觉 **45/0**
- [ ] S13-04 `check-http.ps1` 全量：已在后台跑（60 项，含五首页与五登录），结果待回收；脚本项数未扩到 70
- [ ] S13-05 冷启动 `coldstart.ps1`：本版未跑（新增迁移已在本地与线上分别 `migrate` 验证）
- [x] S13-06 提交推送完成：`20229aa`（S12）、`c850138`（部署修复）；tag **`S8-13-live5`** 已推送（命名与原计划的 `S13-live5` 不同，覆盖 S8~S13 六个阶段）
- [x] S13-07 线上部署与实测：`deploy.sh demo` 完成；门户 + 五首页 + 五登录 + 10 种登录组合 + MES/HR 后台页全部通过（详见规划书 12.11）
- [x] S13-08 文档同步：规划书 v4 的 12.4/12.5/12.7/12.10 已改为实做口径，新增 12.11 上线踩坑记录；任务清单 v4 的 S9~S13 已逐项勾选

---

## 第 9.8 部分｜S14 对齐审计与 MES / HR 完备补全（2026-11-27）

> 完成标记：`S14-align-complete`。口径：三套「对齐型」（ERP ↔ `E:\ddwl-erp\erp`、PM ↔ `E:\my-pm\pm-server-openEuler`、Shop ↔ `E:\erp-shop`）以「路由 / 表 / 视图引用」三层无缺失为对齐验收；「协同办公」「电话会议」「企业微信」「AI 外部 API」「注册页」为显式排除项。两套「新增型」（MES、HR）以 Odoo / ERPNext / OrangeHRM 为参照补全功能闭环。

### A. 对齐审计（9 项）
- [x] S14-A01 脚本 `tools/compare-routes.php`：参考项目原地 `artisan route:list --json --except-vendor` 导出基线，归一口径 = HTTP 方法 + URI（去产品前缀、参数统一 `{*}`）+ `Controller@method`
- [x] S14-A02 脚本 `tools/compare-schema.php`：解析双方 migrations 的 `Schema::create|table` 与 `$table->col`，比对表集合与列集合
- [x] S14-A03 脚本 `tools/compare-views.php`：同名视图比对 `route('x.y')` 引用集合，路由名去产品前缀归一化；缺 60% 以上引用判定为空壳
- [x] S14-A04 ERP 对账：路由 353 / 355（缺 0、多 2 自建，唯一 action 差异是根路由 `HomeController@index` → `LandingController@show`）、表 287 / 287（缺表 0 缺列 0）、视图 171 / 174（缺文件 0、空壳 0）
- [x] S14-A05 Shop 对账：路由 235 / 224（缺 12，全为 `/api/ai/*` 外部数据 API）、表 82 / 82（缺 0 缺列 0）、视图 117 / 117（缺文件 0、空壳 0）
- [x] S14-A06 PM 对账：路由 268 / 196（缺 74 = api 38 + 企业微信 17 + 协同办公 11 + 注册 2 + 2 条 PATCH 形式差异）、表 49 / 41（缺 8 全属 wecom/collab/voice，`users` 缺 4 列同为 wecom）、视图 102 / 97（缺 5、空壳 3，全部落在排除项）
- [x] S14-A07 PM 仅存差异定性：`/admin/departments/{id}`、`/admin/roles/{id}` 参考接 `PATCH|PUT`，演示站只有 `PUT`，功能等价；如需字面一致可改 `Route::match(['put','patch'], …)`
- [x] S14-A08 结论：三套系统差异全部集中在五类已知排除项，除此之外 ERP / Shop 零差异、PM 仅 2 条 HTTP 方法形式差异
- [x] S14-A09 明细落盘：`tools/baseline/ref-{erp,pm,shop}.json` + `diff-*` / `schema-*` / `views-*.txt`，可随时重跑复现

### B. MES 完备补全（8 项）
- [x] S14-M01 缺口定位：对标 Odoo Manufacturing / ERPNext，断点在「报工之后没有闭环」——缺完工入库、批次追溯、计划保养、OEE
- [x] S14-M02 迁移 `2026_11_27_200001`：`finished_receipts`、`lots`、`maintenance_plans`、`maintenance_orders`（已 migrate）
- [x] S14-M03 `CompletionService`：分批入库、超计划量拒绝、入库累加成品库存、入满自动 `completed`、关单校验工序全 done、批次号 `LOT-{Ymd}-{seq}` 与父批次推导
- [x] S14-M04 `MaintenanceService`：到期计划一键生成保养单、同计划已有未完成单则跳过、完成后回写 `last_done_at` / `next_due_at`
- [x] S14-M05 控制器 4 个：`FinishedReceiptController`、`MaintenanceController`、`TraceController`（`lot_no` 路由绑定）、`Reports\OeeController`（A=运行/(运行+停机)、P=理论/运行封顶 100%、Q=合格/(合格+不良)）
- [x] S14-M06 视图 6 个 + 路由 12 条 + 菜单 4 项 + 工单详情「完工入库 / 关单」按钮块
- [x] S14-M07 种子 `CompletionSeeder`：12 个原料批次 + 4 张工单成品入库与父批次链 + 6 条保养计划（2 条已到期）+ 4 张保养单（幂等）
- [x] S14-M08 实测闭环：6 个新页 200；WO9 入 200 → 再入 600 → 累计 800 = 计划量 → 自动 completed；WO17 关单成功；WO9 因 6 道工序未 done 被拦（符合设计）；到期计划重复生成被跳过

### C. HR 完备补全（8 项）
- [x] S14-H01 缺口定位：对标 OrangeHRM（Training / Qualifications / Time-Work Shift）与 Odoo HR（Courses / Skills / Planning），缺培训、技能证书、排班表、员工自助
- [x] S14-H02 迁移 `2026_11_27_210001`：`training_courses`、`training_sessions`、`training_records`、`skills`、`employee_skills`、`certificates`、`shift_assignments`（7 表，已 migrate）
- [x] S14-H03 模型 7 个 + `Employee` 补 `skills()` / `certificates()` / `trainingRecords()` 三个关系
- [x] S14-H04 控制器 4 个：`TrainingController`、`SkillController`（技能矩阵 + 证书台账）、`RosterController`（周视图 + 批量排班 + 状态标记）、`MeController`（按 `employee_no` 关联员工档案做自助首页）
- [x] S14-H05 视图 5 个：`training/index`、`skills/index`、`skills/certificates`（60 天到期高亮、过期红）、`attendance/roster`（周网格 + 点格切状态）、`me/index`（未绑定档案时降级提示）
- [x] S14-H06 路由 8 组 + 菜单 4 项：培训管理 / 技能矩阵 / 证书台账 / 排班表 / 我的自助，全部挂在 `auth:hr` 内、按 `hr.` 前缀命名
- [x] S14-H07 种子 `TrainingSkillSeeder`：6 门课程、5 期场次、50 条培训记录、8 项技能、120 条员工技能、20 张证书（含过期与即将到期各若干）、150 条本周排班（幂等）
- [x] S14-H08 实测：5 个新页全 200；建课程 / 建技能 / 挂等级 / 登记证书 / 批量排班（3 人×5 天=15 条）/ 开培训场次均写库成功，测后已清理脏数据

### D. 回归与交付（5 项）
- [x] S14-R01 `guard.ps1`：**PASS 17 / 0**（G-13 全量 `php -l` 1583 个文件通过）
- [x] S14-R02 视觉 **45/0**、独立性 **31/0**、人设 **10/0**、交叉登录 **5/0**、走查 **17/0**
- [x] S14-R03 `check-http.ps1` 新增 `[3b]` 固定清单 10 项（MES 5 页 + HR 5 页），项数由 60 扩到 70
- [x] S14-R04 提交推送：`c7a971b`（main 分支，已 push `651f20b..c7a971b`），tag 待发版时补
- [x] S14-R05 文档回填：规划书新增 12.12、任务清单新增本节；并记录 Blade 单行 `@php(...)` + 命名空间 → `T_NS_SEPARATOR` 500 这条坑

---


## 第 9.9 部分｜S15 对齐收口与最后一公里补全（2026-11-27 第二段）

> 完成标记：`S15-patch-sop-accrual`。目标：把 PM 的形式差异清零，再给 MES / HR 各补一个"现场真会用"的环节。

### A. 对齐收口（4 项）
- [x] S15-A01 `Route::match(['put','patch'], ...)` 补齐 PM 的 `/admin/departments/{id}`、`/admin/roles/{id}`
- [x] S15-A02 `tools/compare-routes.php` 修正多方法口径：`PUT|PATCH` 拆成单方法键再比对
- [x] S15-A03 重跑三产品对账：ERP 缺 0 / Shop 缺 12（全 AI API）/ PM 缺 72（api 38 + 企微 17 + 协同 13 + 电话会议 2 + 注册 2）
- [x] S15-A04 结论：PM 功能性缺口清零，剩余全为"演示站不复刻的模块"

### B. MES 工艺文件 SOP（7 项）
- [x] S15-M01 迁移 `2026_11_27_220001_create_mes_sop_documents`：`sop_documents`（routing_operation_id / code / title / version / content / file_path / status / effective_on / author）
- [x] S15-M02 模型 `SopDocument`：状态文案、`isEffective()`（生效日未到的算待生效）、`hasFile()`
- [x] S15-M03 控制器 `SopController`：index（按路线看覆盖）/ create / show / store / update / destroy / download
- [x] S15-M04 视图 `sop/index`（工序表 + 覆盖状态 + 覆盖率 KPI）、`sop/form`（登记与编辑共用，含附件上传）
- [x] S15-M05 路由 7 条 + 菜单「工艺文件 SOP」，放在现场组
- [x] S15-M06 种子 `SopSeeder`：40 道工序配 37 份 SOP（留 3 道未下发做演示），6 份带 txt 附件，幂等
- [x] S15-M07 实测：5 类页面 200、登记 / 升版 / 上传附件 / 删除全通；无附件时下载导出 txt

### C. HR 年假核算（5 项）
- [x] S15-H01 `LeaveAccrualService`：五档司龄规则 + 单人应享计算 + 全员预览 + 一键发放（只改 total / remaining）
- [x] S15-H02 `LeaveController::accrual` / `accrualRun` 两个动作，核算年度可切换
- [x] S15-H03 视图 `leaves/accrual`：规则对照 + 差异预览表（应享/现额度/已休/差额/适用档）+ 一键发放
- [x] S15-H04 路由 2 条 + 菜单「年假核算」，放在考勤与假期组
- [x] S15-H05 实测：页面 200、需调整 68 人 → 发放调整 68 / 无变化 8 / 授予 468 天 → 再核算需调整 0 人（幂等）

### D. 回归与交付（4 项）
- [x] S15-R01 `check-http.ps1` 再补 2 项（`/mes/sop`、`/hr/leaves/accrual`），项数 70 → 72
- [x] S15-R02 `guard.ps1` 17/0、视觉 45/0、独立性 31/0、走查 17/0、HTTP 72/0
- [x] S15-R03 提交推送 main（commit 见规划书 12.13 与 git log）
- [x] S15-R04 线上部署：MES/HR 两条新迁移 + SOP 种子，线上页面验证 200

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


---

## 9.14 S16：全面冒烟与三套对齐复核

### A. 冒烟工具与结果（5 项）
- [x] S16-A01 `tools/smoke-all.ps1`：登录后遍历各产品全部无参 GET 页面，按 PASS / FAIL / WARN(403) 归类，明细落 `storage/logs/smoke-<product>.txt`
- [x] S16-A02 线上同口径脚本 `smoke-live.sh`（curl + route:list 解析），排除 login / api / ai / export / download / preview
- [x] S16-A03 本地结果：ERP 131 / PM 62 / Shop 68 / MES 34 / HR 39 = **334 通过，0 失败**
- [x] S16-A04 线上结果：与本地完全一致 334 / 0 / 3 WARN
- [x] S16-A05 3 个 WARN 定性：POS 挂单取单 / 支付方式 / 交班汇总，admin 非当前开班收银员，按设计 403

### B. 本轮修掉的问题（7 项）
- [x] S16-B01 ERP 侧栏：`NavigationService::resolveRouteName()` 加 `erp.` 前缀兜底 → 3 组 28 项、skipped 0
- [x] S16-B02 ERP 账号：`PositionRoleSeeder` 按 `positions.role_names` 同步 29 个账号 + 无岗位兜底综合岗
- [x] S16-B03 ERP 运维页 500：表计数按驱动分支（sqlite / pgsql / mysql）
- [x] S16-B04 ERP BOM 详情 500：`BomStatus::label()` 静态调用改 `tryFrom()->label()`
- [x] S16-B05 ERP 业务详情 404：admin 叠加总经理岗（`data_scope=all`），侧栏 31 项
- [x] S16-B06 PM 9 个 500：8 个 my-tasks 状态页补 `pm::` 视图前缀；支援中心补建 `support/department-files` 视图
- [x] S16-B07 Shop 2 处：到货单 create 无参回列表；头像接口回落默认头像 + 补齐 5 张 SVG

### C. 三层对齐复核（3 项）
- [x] S16-C01 路由：ERP 缺 0；PM 缺 72（全排除项）；Shop 缺 12（全 `/api/ai/*`）
- [x] S16-C02 表：ERP 287 表全齐；Shop 82 表全齐；PM 仅 `users` 缺 4 个 `wecom_*` 列
- [x] S16-C03 视图引用：ERP 148 齐；Shop 98 齐；PM 2 个 wecom 视图缺引用

### D. 交付（4 项）
- [x] S16-D01 提交推送 main（`6eaddb4`、`c6e32d2`、`e4ee001`），部署包排除出库
- [x] S16-D02 线上部署：sudo 解包 → `composer install --no-dev` → 清 `bootstrap/cache` 的 packages/services → seed → config/view/route 缓存 → chown → reload
- [x] S16-D03 线上 `.env` 从 `local/true` 改回 `production/false`
- [x] S16-D04 线上按真实 id 验证 BOM 5 / 采购单 3 / 生产单 3 均 200；侧栏 31 项

### E. 遗留（2 项）
- [ ] S16-E01 含参 125 条详情页未全量自动巡检（参数类型异构，固定 id 会造假 404），本轮抽样 60 条
- [ ] S16-E02 PM 知识库当前 0 条文档，演示页为空态（功能齐、缺演示数据，待补种子）

---

## S17 演示数据覆盖 + 五系统管理后台统一

目标：演示数据达到"每个岗位都有账号、每类单据都有链路"；五套系统的 `/{产品}/admin` 后台只做系统管理、不带任何业务入口。

### A. 演示数据覆盖（6 项）
- [x] S17-A01 统一口径写入规划书 12.15：用户覆盖岗位、数据覆盖场景、允许虚拟企业名、幂等、可复跑
- [x] S17-A02 页面静态文案去真实公司名：49 个文件 59 处，另把 11 行库内企业名快照改为"演示××"虚构名（`tools/purge-real-company-names.php`）
- [x] S17-A03 ERP 覆盖种子三批（`DemoCoverageSeeder` / `DemoCoverageExtraSeeder` / `DemoCoverageFinalSeeder`），空列表 94 → 25
- [x] S17-A04 PM 覆盖种子（`DemoCoverageSeeder` 12 个方法），空列表 38 → 24
- [x] S17-A05 Shop 覆盖种子（`DemoCoverageSeeder` 7 个方法，含 POS 开班），空列表 20 → 19
- [x] S17-A06 状态枚举归一（`tools/normalize-demo-status.php`）+ 审批代批人（`tools/patch-approval-delegates.php`），本地与线上各跑一次

### B. 管理后台统一（7 项）
- [x] S17-B01 统一皮肤 `resources/views/system/layout.blade.php`：深色侧栏、固定 8 项后台菜单、扩展维护组、返回门户与退出
- [x] S17-B02 统一控制器 `SystemConsoleController` + 适配层 `SystemConsole`（探测五库的用户 / 部门 / 岗位 / 角色列名并归一化）
- [x] S17-B03 统一路由：`/{产品}/admin`（首页 / 用户 / 部门 / 岗位 / 角色 / AI 导入引导 / 运维监控 / 系统设置），已被模块占用的路径沿用原页面
- [x] S17-B04 41 个原后台视图 `@extends` 切到 `system.layout`，后台侧栏不再混入前台业务菜单
- [x] S17-B05 Shop 新建 `departments` / `positions` 并给 `users` 加归属列；PM 新建 `positions` 并加 `position_id`（迁移 + `OrgDemoSeeder`）
- [x] S17-B06 后台菜单补齐：ERP 新增用户 / 角色 / 运维 / 设置；PM 新增岗位 / AI 引导 / 运维 / 设置；Shop 从 0 建全套；MES / HR 新增 AI 引导 / 运维 / 设置
- [x] S17-B07 访问控制：仅 admin 或角色含 admin/super/sys 可进后台，其余 403

### C. 验证（3 项）
- [x] S17-C01 本地探测 46 个后台页面全 200（`tools/probe-admin-all.ps1`）
- [x] S17-C02 侧栏核对：五系统均 8 项固定菜单 + 各自扩展项（ERP 11 / PM 12 / Shop 11 / MES 9 / HR 9）
- [x] S17-C03 后台页面无业务入口链接（扫描渲染 HTML 中的 `/erp/sales`、`/pm/project`、`/shop/pos`、`/mes/work-orders`、`/hr/employees` 等）

### D. 交付
- [x] S17-D01 提交并推送 main
- [x] S17-D02 线上部署：迁移 + 组织种子 + 配置缓存，reload php-fpm / nginx
- [x] S17-D03 线上冒烟：五系统登录与后台页面、业务关键页全绿

## S18 前端移动端自适应
目标：五套系统全部前端页面在手机竖屏（≤991.98px）可用，桌面端零回归。

### A. 适配层
- [x] S18-A01 新建 `public/assets/shared/css/responsive.css`：断点 991.98px（后台 900px），所有规则只在媒体查询内
- [x] S18-A02 新建 `public/assets/shared/js/responsive.js`：抽屉开关、遮罩、Esc/点菜单关闭、表格自动包 `.table-responsive`
- [x] S18-A03 五套布局 + 系统后台皮肤 + 门户四个整页视图 + 五个登录页统一注入适配层
- [x] S18-A04 侧栏抽屉化：ERP `.sidebar`、Shop `.app-sidebar`、MES `.mes-side`、HR `.hr-side`、后台 `.sys-side`；ERP / Shop 复用自带汉堡按钮，MES / HR / 后台补右下角浮动按钮
- [x] S18-A05 MES / HR 顶栏窄屏换行，抽屉高度撑满视口；表格（含 MES/HR 自绘 `mes-table`）统一横向滚动

### B. 验证
- [x] S18-B01 线上 390×844 逐页核对：ERP 首页/后台、Shop 仪表盘/后台/POS、MES、HR、PM、门户均无横向溢出（`scrollWidth = 390`）
- [x] S18-B02 抽屉实测：ERP、Shop、MES、HR、系统后台点按钮滑出、点遮罩关闭；PM 走自带 offcanvas
- [x] S18-B03 桌面 1280 宽回归：ERP 侧栏 fixed 240、Shop static 248、MES sticky 232，布局未变
- [x] S18-B04 线上冒烟 124 项全绿

### C. 交付
- [x] S18-C01 提交并推送 main
- [x] S18-C02 线上部署（静态资源 + 视图缓存清理 + reload php-fpm）

### D. 追加：密表卡片流与 POS 手机极简
- [x] S18-D01 密表（≥6 列、无合并单元格、≤300 行）在 ≤767.98px 转卡片流，原表隐藏但保留数据（JS 生成 `.mcards`）
- [x] S18-D02 POS 手机端补底部浮动条：已选件数 + 应收合计 + 「商品 / 结算」跳转，合计随购物车实时同步
- [x] S18-D03 验证：ERP 用户管理 30 行生成 30 张卡片、原表已隐藏；POS 浮动条渲染且合计 ￥0.00；线上冒烟 124 项全绿
