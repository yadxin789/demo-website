# demo-website 五产品独立演示站 · 项目规划书 v4

> v4 在 v3 全部结论之上增补**第 12 章（二期需求，2026-11-27）**：门户文案精简、五产品各自拥有"对外首页"（卡片不再直落登录页）、五套布局统一以 `E:\my-pm\pm-server-openEuler` 为基准、登录统一支持"邮箱或用户名"、ERP/Shop 与参考项目对账、MES 与 HR 补齐后台管理（用户/部门/岗位/角色）成为完备独立系统。v3 正文继续有效，冲突处以第 12 章为准。
> 配套：`demo-website-任务清单-v4.md`、`MES-产品规划书.md`、`MES-任务清单.md`、`HR-产品规划书.md`、`HR-任务清单.md`。
> 本文件保留 v2/v3 中"命名规范、跨请求键规范、鉴权、SQLite 参数、部署配置"等不冲突内容。

---

## 第 0 章｜决策基线（v3 定稿）

| # | 决策 | 对方案的影响 |
|---|---|---|
| 1 | MES、HR **不从 ERP 剥离** | ERP 保留 52 控制器 / 136 模型 / 84 迁移 / 47 视图目录，`production.*`（26 条）、`quality.*`（8 条）、`hr.*`（7 条）、`hra.*`（6 条）路由全留。**S2 一次整块搬完，无 S3/S4 拆分风险** |
| 2 | MES、HR 作为**独立新产品**从零开发 | 各自独立的库、用户、路由、视觉、数据模型；功能量需大于 ERP 内置模块，否则客户会问"为什么独立产品反而更弱" |
| 3 | S0 只做 MES/HR 的**预留位** | 建目录、连接名、guard、空库文件、`products.php` 记录（`enabled=false`）。**不做登录页、工作台、任何 UI** |
| 4 | **不做空白卡片入口** | 门户首期只显示 3 张卡（ERP/PM/Shop）；MES/HR 记录存在但 `enabled=false`，卡片自动隐藏、URL 返回 404 |
| 5 | 三个参考项目做完后**紧接着**开发 MES、HR | 阶段顺序：三产品上线（里程碑 A）→ MES → HR → 五卡全亮（里程碑 B） |
| 6 | 五产品独立 `users`、独立鉴权 | 五 guard + 五 provider + 五库，不共享账号 |
| 7 | 演示账号密码统一 `123456` | 三项目原本即是该值，零改造成本 |
| 8 | 门户"免登录预览" | `/{x}/preview` 介绍页；预览内容全部来自配置，**不读产品库** |
| 9 | `spatie/laravel-permission` 统一 8.x | ERP 从 6.23 升级，路径已在 erp-pm-soho 验证 |
| 10 | 不做旧 URL 兼容 | 旧路径直接 404 |
| 11 | PM 移除协同办公、电话会议 | 20 个文件 + 5 个迁移 + 2 个配置（§7.1 清单） |
| 12 | 移除全部定时任务 | 31 个 Command + 3 个 `console.php` |
| 13 | 保持原布局样式、最大化复用原代码 | 不重做 UI；**类名/方法名/视图名/路由后缀一律不改，只改前缀**（HC-15） |

### 0.1 这条调整省掉了什么、增加了什么

| 省掉 | 增加 |
|---|---|
| S3「切 MES」的迁移摘块风险（原 R1 高风险项） | MES 从零开发约 50d、HR 约 36d（v2 里这两个是 5d + 3.5d） |
| ERP↔MES/HR 断裂点补丁（原 S3-16 / S4-12） | 两个新产品的完整数据模型、页面、种子数据、权限体系设计 |
| MES/HR 与 ERP 的跨域耦合排查 | 与 ERP 功能重叠的"边界话术"设计（客户会问"ERP 里已有生产，为什么还要 MES"） |

> **总工期从约 40d 变为约 116d（约 5.5 个月）**。这是"不做剥离、独立重做"的必然代价，需在对外承诺前确认。

---

## 第 1 章｜产品矩阵与首发策略

### 1.1 五个产品的定位（按"客户现状"分流）

**关键变化**：MES 的目标客户不是"买我的 ERP 的人"，而是**已经有其他厂商 ERP 的客户**。这决定了两件事：
1. MES 必须是完全独立系统——不依赖我的 ERP，客户不需要换掉手里的金蝶/用友/管家婆；
2. MES 必须具备"把现有数据搬进来"的能力（Excel 导入，见 MES 规划书 §0.4），否则话术落不了地。

| 产品 | code | 定位 | 卖给谁（客户画像） | 报价逻辑 |
|---|---|---|---|---|
| 电子制造 ERP | `erp` | 供应链 · 生产计划 · 财务一体化 | **什么都没有的客户**——首次上系统 | 一站式，客单价最高 |
| 智能制造 MES | `mes` | 车间执行 · 工单 · 质量 · 设备 · 排产 | **已有其他 ERP 的客户**——补车间执行层 | 增量销售，不动客户现有系统 |
| 人事管理 HR | `hr` | 组织 · 员工 · 考勤 · 薪资 · 招聘绩效 | 已有 ERP 但人事功能弱；或纯人力需求 | 单点产品，易成交 |
| 研发项目管理 | `pm` | 项目 · 任务 · 工时 · 研发流程 | 研发型团队、软件/设计公司 | 单点产品，易成交 |
| 百货零售 ERP | `shop` | POS 收银 · 门店 · 仓储 · 日结 | 零售门店、连锁小超市 | 单点产品，客单价低但决策快 |

### 1.2 销售路径（这是五个产品存在的第一层理由）

```
客户说"我已经有 ERP 了"           → 推 MES（车间执行补位）
客户说"什么都没有"                → 推 ERP（一站式）
客户说"有 ERP 但人事一团糟"        → 推 HR
客户说"我们做研发/接项目"          → 推 PM
客户说"我开店的"                  → 推 Shop
```

**这条路径解释了为什么"完全独立"是必需的，而不是洁癖**：如果 MES 依赖自己的 ERP 才能跑，它就卖不给"已经有 ERP"的客户——而那正是 MES 唯一的目标群体。

### 1.3 完工节奏（两次里程碑，均不对外）

```
里程碑 A（约 26.5d）：三卡完工 — 内部可用
  /                门户 3 张卡（erp / pm / shop）
  /erp /pm /shop   三套独立产品全功能
  MES/HR           enabled=false，无任何入口（HC-29）

里程碑 B（约 +90d）：五卡完工 — 对外统一发布
  /mes /hr         两个新产品
  /                门户 5 张卡
```

**策略**：不做"三卡先上线接客、后补 MES/HR"的分两批发布。理由：
- 当前无业务资源，接客能力为零，早发布不产生实际收益，反而增加"上线了没人管"的维护负担；
- 分两次发布会让早期访客先看到"只有三个产品"，之后才补两个，削弱"产品线丰富"的第一印象；
- 产品完整度本身就是最好的销售材料。

**但有一件事必须提前做（与开发并行的长周期事项）**：

| 事项 | 提前多久 | 原因 |
|---|---|---|
| 域名注册 + ICP 备案 | **立即启动**，备案约 20 个工作日 | 腾讯云轻应用服务器在国内，无备案无法绑定域名对外访问；这不是"跑业务"，是"产品存在的前提"，被它卡住最冤枉 |
| 企业主体/个人备案材料准备 | 立即 | 个人备案不能放经营性内容，若将来要收费需企业主体，提前想清楚 |
| 站点统计与基础 SEO | 里程碑 A | 不推也先让搜索引擎能收录；越早收录，越早有自然流量 |

> 结论：**开发按"全做完再对外"排，但域名备案按"今天就开始"排。** 两者不冲突，后者几乎不占开发时间。

### 1.4 重叠答疑话术（客户会追问的两句）

| 场景 | 话术 |
|---|---|
| "我已经有 ERP 了，为什么还要你的 MES" | "正因为您有 ERP，才更需要 MES。ERP 管的是计划和账，车间里谁在做、做到哪道工序、这批不良是谁做的，它管不到。MES 不动您的 ERP，只把车间这一层补上——您的 ERP 继续用，我这边负责车间" |
| "你的 ERP 和你自己的 MES，是不是一个东西拆成两个卖" | 演示时用数据证明：两家公司名不同、物料编码规则不同（`MAT-` vs `MJ-`）、单号规则不同。**两个系统各自独立运行，互不依赖**（HC-32） |
| "能不能和我的 ERP 打通" | "可以。演示版先用 Excel 导入导出（您从现有 ERP 导出物料、BOM、工单，导进我的 MES），正式版可做接口对接。这样您不用等我开发接口就能先用起来" |

### 1.4 五产品的"公司人设"隔离（避免客户觉得数据串了）

演示数据必须像**五家不同公司**，这是"看起来像五个产品"的第二层（第一层是视觉）：

| 产品 | 演示公司 | 行业 | 员工/组织规模 |
|---|---|---|---|
| ERP | 东莞恒创电子有限公司 | 电子制造 | 部门 8 个、员工 30 人 |
| PM | 深圳智软科技有限公司 | 软件研发 | 项目 6 个、成员 12 人 |
| Shop | 悦家连锁百货 | 连锁零售 | 门店 3 家、员工 20 人 |
| MES | 精工精密制造（苏州） | 精密加工 | 产线 4 条、工人 60 人 |
| HR | 华瑞集团（控股） | 综合集团 | 部门 12 个、员工 80 人 |

**硬要求**：五套演示数据里的人名、公司名、物料编码规则、单号前缀都不同。ERP 与 MES 的物料绝不能编码相同——否则客户一眼看出同源。

---

## 第 2 章｜ERP 不剥离的连带影响（必须逐条落实）

ERP 保持原样，意味着 S2 搬完后它内部仍有 `Models\Production`(13)、`Models\Quality`(4)、`Models\Hr`(7) 与 `hr_*` 表。由此产生四件事：

| # | 事项 | 处理 |
|---|---|---|
| 1 | ERP 的 `production.*`/`quality.*`/`hr.*`/`hra.*` 路由全保留 | S2 不删任何路由；路由条数 = 原始全量 |
| 2 | ERP 的 `Models\Hr` 与 HR 产品的 `Models\Hr` 类名相同 | 不在同一个命名空间：`App\Modules\Erp\Models\Hr\HrEmployee` vs `App\Modules\Hr\Models\Employee`。**HR 产品不复用 ERP 的类名，重新设计** |
| 3 | ERP 的 `Stocktake`（盘点）留在 `Models\Production` | 保持原样，不做归属迁移（v2 的裁定作废） |
| 4 | ERP 与 MES 都要物料/BOM | 各自一份，编码规则不同（MES 用 `MJ-` 前缀，ERP 用 `MAT-`） |

---

## 第 3 章｜目录结构（v3）

与 v2 的差异只有两处：`Modules/Mes`、`Modules/Hr` 由"空壳"改为"预留位"，并标注为后期开发。

```
E:\demo-website\
├── app\
│   ├── Core\                        唯一共享层（≤15 类）
│   │   ├── Http\Controllers\{PortalController, PreviewController}.php
│   │   ├── Http\Middleware\{SetProductContext, EnsureProductEnabled, GuardByProduct}.php
│   │   ├── Providers\CoreServiceProvider.php
│   │   └── Support\{Product, ProductRegistry, ProductPaths}.php
│   │
│   ├── Modules\
│   │   ├── Erp\                      ★ S2 整块搬（含 Production/Quality/Hr 全部保留）
│   │   │   ├── BaseModel.php
│   │   │   ├── Http\Controllers\      52 个（一个不删）
│   │   │   ├── Models\                136 个 / 26 子域（Hr、Production、Quality 全留）
│   │   │   ├── Services\              31 个
│   │   │   ├── Routes\web.php         900 行全量
│   │   │   ├── Database\Migrations\   84 个（一个不拆）
│   │   │   ├── Database\Seeders\
│   │   │   ├── Resources\views\       47 目录
│   │   │   └── Config\erp.php
│   │   │
│   │   ├── Pm\                       S1 搬（裁 collab/voice/schedule）
│   │   ├── Shop\                     S3 搬
│   │   │
│   │   ├── Mes\                      ◇ 预留位（S0 建，里程碑 A 前不启用）
│   │   │   ├── BaseModel.php           $connection='mes'
│   │   │   ├── Models\User.php         仅账号模型
│   │   │   ├── Routes\web.php          空文件（仅有注释头）
│   │   │   ├── Database\Migrations\    空目录
│   │   │   ├── Providers\MesServiceProvider.php（enabled 判断即退出）
│   │   │   └── Resources\assets\mes.css 占位
│   │   │
│   │   └── Hr\                       ◇ 预留位（同 Mes 结构）
│   │
│   └── Providers\AppServiceProvider.php
│
├── config\
│   ├── products.php                   ★ 五条记录，Mes/Hr 的 enabled=false
│   ├── database.php                   五连接（mes/hr 连接已配但库为空）
│   └── auth.php                       五 guard（mes/hr guard 已配）
│
├── database\
│   ├── erp.sqlite  pm.sqlite  shop.sqlite
│   └── mes.sqlite  hr.sqlite          空库文件（S0 创建，S5/S6 才有表）
│
├── public\assets\{shared,portal,erp,pm,shop,mes,hr}\
├── resources\views\portal\{home,preview}.blade.php
├── storage\{app,framework\{sessions,cache,views},logs}\{erp,pm,shop,mes,hr}\
└── ...（其余与 v2 §3 一致）
```

### 3.1 预留位的边界（HC-29）

预留位允许存在的东西**只有**：

| 允许 | 不允许 |
|---|---|
| `BaseModel`、`Models/User` | 任何业务模型 |
| 空的 `Routes/web.php`（可含注释） | 任何路由 |
| 空的 `Migrations` 目录 | 任何迁移 |
| `Providers/{X}ServiceProvider`（启动时检查 `enabled`，false 则直接 return） | 任何视图、任何控制器 |
| `assets/{x}/{x}.css` 占位 + logo/favicon 占位 | 登录页、工作台 |
| `config/products.php` 记录（`enabled=false`） | 门户卡片（由 enabled 自动控制） |

**理由**：一旦预留了 UI，就会出现"点进去是空的"或被错误点亮的风险；配置层预留足以让 S5/S6 直接开始写业务，不需要回头改基础设施。

---

## 第 4 章｜硬约束（v3 增补）

v2 的 HC-01~HC-28 全部继续有效。本次新增：

| 编号 | 规则 | 检查 |
|---|---|---|
| **HC-29** | MES/HR 在里程碑 A 前只允许存在"预留位"内容（§3.1 白名单）；不得有任何路由、控制器、视图 | 目录白名单比对 |
| **HC-30** | `enabled=false` 的产品：门户不显示卡片、路由不注册、`/{x}/*` 返回 404（含 `/{x}/preview`） | 开关切换验证 |
| **HC-31** | MES/HR 新产品的类名**不得复用** ERP 内的同名类设计（如 `HrEmployee`）；数据模型独立设计，字段可按需参考但表结构独立 | 类名/表名比对 |
| **HC-32** | 五产品的演示数据必须"公司人设隔离"（§1.4）：公司名、人名、编码规则、单号前缀全不同 | 数据抽查 |
| **HC-33** | ERP 不得因 MES/HR 的存在而删改任何原有功能（`production.*`/`quality.*`/`hr.*`/`hra.*` 路由条数必须与原始一致） | 路由差集 |
| **HC-34** | 新产品开发期间（里程碑 A→B），三卡产品必须保持可演示状态，不得因新增 MES/HR 而改坏 | 每次合并后回归三卡 |
| **HC-35** | spatie 权限缓存全局只有一份、不区分数据库：切库中间件（`SetProductContext`）必须在每次切库后 `forgetCachedPermissions()`；重建库后必须 `permission:cache-reset`。否则门户请求会先用默认库把缓存建起来，产品权限页全部 403 | 先访问门户、再登录各产品访问权限页 |

---

## 第 5 章｜阶段重排（v3）

| 阶段 | 目标 | 工时 | 里程碑 |
|---|---|---|---|
| S-PRE | 环境与仓库 | 1.5d | `S0-pre` |
| S0 | 骨架：门户 3 卡 + 3 套壳 + MES/HR 预留位 | 5d | `S0-portal` |
| S1 | 搬 PM（含裁功能） | 4d | `S1-pm` |
| S2 | 搬 ERP（**整块，一个不删**） | 7d | `S2-erp` |
| S3 | 搬 Shop | 5d | `S3-shop` |
| S4 | 三产品收尾：品牌、演示数据、内部验收 | 4d | **`S4-live3`（里程碑 A）** |
| S5 | MES 全新开发（含 Excel 导入导出） | 50d | `S5-mes` |
| S6 | HR 全新开发（含 Excel 导入导出） | 36d | `S6-hr` ✅ 已完成 |
| S7 | 五卡整合与对外发布（含线索捕获、备案信息） | 4d | **`S7-live5`（里程碑 B）** ✅ 已完成 |
| **合计** | | **116d**（约 5.5 个月） | |

> 相对 v2 的 35d：省掉 S3/S4 剥离（-8.5d）、新增 MES/HR 开发（+92.5d）、新增五卡整合（+4d）。**净增约 81d。**
>
> **关于这个长度的判断**：在"接不到业务、先完善产品"的前提下，5 个月是可以接受的。但要守住一条：**每个阶段末都必须有可运行、可截图、可演示的东西**，不能出现"做了三个月还没一页能看"的状态。这也是把里程碑定为"完工节点"而非"上线节点"的意义——它用来防止无限打磨。

### 5.1 关键约束

- S2 完成前不能开始 S5（MES 需要参照 ERP 的数据模型与 UI 规范，且要复用同一套骨架经验）
- S5 与 S6 可并行（若有两个开发环境），但建议串行：HR 可复用 MES 开发中沉淀的 CRUD/权限/种子模板
- 里程碑 A 之后，S5/S6 期间任何变更都不得破坏三卡（HC-34）

---

## 第 6 章｜命名与跨请求键（v3 补 MES/HR）

在 v2 §5 的基础上补两行：

| 对象 | 规范 | 例 |
|---|---|---|
| MES 路由 | `mes.*`，URL `/mes/*` | `mes.work-orders.index` |
| HR 路由 | `hr.*`，URL `/hr/*` | `hr.employees.index` |
| 会话/缓存/日志 | `storage/{x}/...` | `storage/framework/sessions/mes` |
| 表名 | MES 用业务化命名（`work_orders`/`routings`/`quality_inspections`）；HR 用 `employees`/`attendances`/`payrolls`（**不加 hr_ 前缀**，因为独立库） | 与 ERP 的 `hr_employees`/`production_orders` 明确区分（HC-31） |

---

## 第 7 章｜功能裁剪（不变，v2 §7 继续有效）

PM 删协同办公/电话会议（20 文件）、全局删定时任务（31 Command + 3 console.php）。
**新增一条**：ERP 因不剥离，其 `routes/console.php`（51 行 / 11 个 Command）同样要清空调度——但 ERP 的 `AcceptanceCleanCommand`、`GuardCheck` 等若被业务代码调用，保留类、只删 `Schedule::` 注册。

---

## 第 8 章｜数据设计（v3）

| 库 | 建库时机 | 表规模预估 |
|---|---|---|
| `erp.sqlite` | S2 | 84 迁移全部表（含 `hr_*`、`production_*`、`aps_*`、`quality_*`） |
| `pm.sqlite` | S1 | 44 迁移 |
| `shop.sqlite` | S3 | 69 迁移 |
| `mes.sqlite` | S5 | 约 22–26 张表（新建，见 MES 规划书） |
| `hr.sqlite` | S6 | 约 18–22 张表（新建，见 HR 规划书） |

**`mes` 与 `hr` 的空库文件在 S0 就创建**，但 S0~S4 期间不建任何表（保持空文件）。这样 S5/S6 开始时不需要回头改配置。

---

## 第 9 章｜发布与回滚

| 节点 | 动作 |
|---|---|
| 里程碑 A | tag `S4-live3`，此时对外可演示 3 产品；备份三库 |
| S5/S6 期间 | 每次合并前回归三卡（HC-34）；每完成一个模块打 tag |
| 里程碑 B | tag `S7-live5`，五卡全亮（冷启动演练 + 五产品 HTTP 验收 60/0 通过） |
| 回滚 | 任一里程碑失败 → 回退到上一个 tag + 恢复库快照 |

---

## 第 10 章｜风险登记（v3）

| # | 风险 | 概率 | 影响 | 缓解 |
|---|---|---|---|---|
| **R1'** | MES/HR 从零开发超期（功能蔓延） | **高** | 高 | 两份独立规划书都定义"演示版 MVP 边界"，超出即砍；每产品先做通一条主链路再补页面 |
| R2 | ERP permission 6→8 崩塌 | 中 | 高 | 8.3.2 已验证 + S2 权限烟测 |
| R3 | 三卡上线后被 MES/HR 开发影响（HC-34） | 中 | 高 | 每次合并跑三卡回归 |
| R5 | 客户质疑"ERP 有生产/人事，为何还要 MES/HR" | **高** | 中 | §1.3 话术 + §1.4 人设隔离 |
| R11 | 预留位被"顺手"塞进 UI，形成空白入口 | 中 | 中 | HC-29 白名单检查 |
| R12 | MES/HR 与 ERP 数据看起来同源 | 中 | 中 | HC-32 编码规则/公司名/人名隔离 |
| R13 | 五套演示数据维护成本（改一次要改五处） | 中 | 低 | 每产品独立 Seeder，互不引用；文档记录人设表 |
| R14 | 单机资源（5 套应用） | 低 | 中 | PHP-FPM `max_children=4`、OPcache、`sync` 队列 |

---

## 第 11 章｜验收（v3）

### 里程碑 A（三卡）
- [ ] 门户 3 卡，MES/HR 不显示且 URL 404
- [ ] 三产品各自独立登录、独立库、独立视觉
- [ ] PM 无 collab/voice、全局无定时任务
- [ ] G-01~G-15 全绿
- [ ] 单 vhost 跑起三产品

### 里程碑 B（五卡）
- [ ] 门户 5 卡，五产品独立登录/库/视觉/演示数据
- [ ] MES 与 HR 各能跑通一条完整业务链路（见各自规划书）
- [ ] 五账号交叉登录全部被拒
- [ ] ERP 路由条数与原始一致（HC-33）
- [ ] 五产品"公司人设"不串（HC-32）
- [ ] 客户走查无"同一系统"感


---

## 第 12 章｜二期需求（2026-11-27 增补，v4 生效）

> 本章来自用户 7 条明确指令 + 1 条补充指令，逐条给出落点、实现口径与验收方式。与 v3 正文冲突处，以本章为准。

### 12.1 需求与落点对照

| # | 用户要求 | 落点 | 阶段 |
|---|---|---|---|
| 1 | 删除门户首页"一个站点，5 套独立系统…"整段文案 | 门户 `portal/home.blade.php` 删除该段，卡片区直接承载信息 | S8 |
| 2 | 五套演示站布局样式统一，以 `E:\my-pm\pm-server-openEuler` 为基准 | 统一皮肤 `assets/shared/css/shell.css` 注入五个 layout（只改视觉，不动骨架） | S10 |
| 3 | 登录都支持"邮箱或用户名" | 五产品登录改统一判定 + 三库补字段 | S9 |
| 4 | 卡片不直落登录页，先到参考项目首页；无首页的复制 PM 首页改文本 | 五产品新增 `/{p}/` 对外首页 | S8 |
| 5 | 电子厂 ERP 未与 `E:\ddwl-erp\erp` 对齐 | 对账脚本 + 门面/外框补齐（功能已 0 缺失，见 12.5） | S11 |
| 6 | MES、HR 没有后台管理菜单（用户/部门/岗位/角色） | 两产品各补齐四项 CRUD + 权限 + 审计 | S12 |
| 7 | 修改规划书与任务清单，按新清单执行 | 本文件 v4 + `demo-website-任务清单-v4.md` | 即时 |
| 补 | "百货零售 ERP"卡片指向的站点要与 `E:\erp-shop` 对齐 | Shop 首页取 `erp-shop/landing`，功能按 erp-shop 基线对账 | S8 / S11 |

### 12.2 门户首页（S8）

- 删除文案：`一个站点，5 套独立系统` 及其后两行说明（"每张卡片背后都是一套完整的企业系统…"），门户首页不再出现"一个站点""同一套系统"这类暗示。
- 卡片保留五张，每张只放：产品名、tagline、一句适用对象、`查看介绍 →`。
- 卡片链接由 `/{p}/login` 改为 `/{p}/`（产品首页）。
- **HC-40**：门户任何位置不得把五个产品描述成"同一套系统的多个模块"；卡片不得直链登录页。

### 12.3 五产品对外首页（S8，本次最大新增）

统一规则：

1. URL = `/{p}/`（GET，**免登录可看**）。
2. 已登录访问 `/{p}/` → 302 到该产品工作台（ERP `/erp/home`，其余 `/{p}/dashboard`）。
3. 首页**不查任何产品业务库**，只读 `config/products.php` 与 `config/portal.php`，避免未登录请求触发迁移/查询（延续 HC-14 精神）。
4. 首页 CTA：`进入演示登录` → `/{p}/login`；页脚 `← 返回产品列表` → `/`。
5. 首页显示演示账号与统一密码 `123456`（HR 显示 `admin@hr.demo`）。
6. 视觉走共享壳（12.4），品牌色取 `products.{p}.theme.primary`。

内容来源：

| 产品 | 参考首页 | 处理方式 |
|---|---|---|
| PM | `E:\my-pm\pm-server-openEuler\resources\views\welcome.blade.php`（21KB 营销首页） | 移植区块顺序，改公司/产品文案 |
| ERP | `E:\ddwl-erp\erp` 的 `/` 是登录后工作台（`home.blade.php`），**无对外首页** | 按规则复制 PM 首页结构，文本改为"电子制造 ERP" |
| Shop | `E:\erp-shop\resources\views\landing.blade.php`（4.2KB 落地页） | 移植，文本改为"百货零售 ERP" |
| MES | 无 | 复制 PM 首页结构，文本改为"智能制造 MES" |
| HR | 无 | 复制 PM 首页结构，文本改为"人事管理 HR" |

五份首页共用的区块顺序（保证"看起来是同一家公司的产品线"，但每套是独立系统）：

1. 品牌区：产品名 + tagline + 公司人设 + `进入演示登录`
2. 一句话说明：这套系统解决什么问题（60 字内）
3. 能力清单：3~4 组，每组 4 条，取 `config('products.products.{p}.features')`
4. 适用 / 不适用：各 2 条
5. 演示账号卡：账号、密码、登录入口
6. 页脚：返回产品列表、邮箱与微信（读 `config/portal.php`）

**HC-41**：五产品首页必须是纯静态渲染（不含 DB 查询、不含登录态分支以外的业务逻辑），且首屏可在 `APP_ENV=production` 下 200。

### 12.4 登录统一"邮箱或用户名"（S9）

前端：

- label 统一 `邮箱或用户名`，placeholder `admin 或 admin@erp.demo`（按产品替换域名）。
- **输入框 `name` 保留各产品原值**（ERP/MES/HR 为 `account`，PM/Shop 为 `username`）：不改成统一的 `login`，避免波及 200+ 存量视图与既有表单，也避免把"统一文案"扩大成"重写表单层"。
- 五个登录页文案、按钮、错误提示一致，仅品牌色与产品名不同。

服务端（五产品同一套判定，写在各自 Controller，逻辑相同）：

1. 含 `@` → 先按 `email` 精确匹配（大小写不敏感）。
2. 不含 `@` → 按 `username`（ERP/Shop/PM）或 `username`（MES/HR 新列）匹配。
3. 两者都试仍失败 → 统一错误 `账号或密码不正确`。
4. 限流键 `login:{login}|{ip}`，5 次/分钟，命中 429 前给出剩余秒数。
5. 登录成功清空限流桶；失败才 `hit`。

字段补齐（迁移 + 种子回填，只动三个库）：

| 产品 | 现状 | 动作 |
|---|---|---|
| ERP | `username` + `email` 均有 | 无需改表，seeder 给 email 赋 `admin@erp.demo` |
| Shop | `username` + `email` 均有 | 无需改表，seeder 给 email 赋 `admin@shop.demo` |
| PM | 只有 `username`，**无 email** | 新增 `users.email`（nullable、unique），admin → `admin@pm.demo` |
| MES | 只有 `email`/`name`，**无 username** | 新增 `users.username`（nullable、unique），admin → `admin` |
| HR | 只有 `email`，**无 username** | 新增 `users.username`（nullable、unique），admin → `admin` |

**HC-42**：演示账号必须"用户名与邮箱都可登录"，密码仍统一 `123456`；三张新列必须进冷启动脚本与 `deploy.sh demo`。

### 12.5 布局统一（S10，PM 风格为唯一基准）

基准特征（取自参考项目布局）：

- 左侧固定深色侧栏，渐变 `#1a1a2e → #16213e`，分组标题小号灰字，激活项左侧 3px 品牌色高亮条。
- 顶部白色导航条：产品名 + 用户下拉（资料/改密/退出）。
- 内容区留白 24px，卡片圆角 8px、边框 `#e9ecef`、阴影极轻，页标题 15px 粗体。
- Bootstrap 5 + FontAwesome 6，**静态资源全部本地托管**（沿用既有 HC，禁 CDN）。
- 移动端 `<768px` 用 offcanvas 抽屉侧栏。

实现拆解（**已实施口径**：只统一皮肤，不重写骨架）：

1. 新增 `public/assets/shared/css/shell.css`：只做"看起来是同一套系统"的部分 —— 侧栏渐变与激活条、顶栏、内容区留白、卡片圆角与阴影、表头底色、按钮圆角、表单 focus 色。
2. 五个 layout 在 `</head>` 末尾（各自内联 CSS 之后）插入 `--brand/--brand-dark` 变量与 `shell.css` 链接，保证覆盖顺序正确。
3. **不改侧栏几何宽度、不重写 layout 结构**：200+ 业务视图是按各自侧栏宽度写的，换骨架会挤压表格与看板；皮肤层改动不触及盒模型，业务视图 0 改动。
4. 品牌色取自 `config('products.products.{p}.theme')`，五产品各注入一份变量，其余样式共用。

与原计划的差异（记录在案）：原计划"五个 layout 全部 `@extends('shared::shell')`"未执行，理由是重写骨架的回归面（200+ 视图 × 5 产品）远超单阶段可验证范围；改以皮肤统一达成"同一套系统"的视觉目标，且已验证 45 项视觉检查全绿。

**HC-36（修订）**：五产品外框的视觉规范只有一个来源 `public/assets/shared/css/shell.css`，禁止各产品再自写一套侧栏/顶栏配色；但布局骨架仍由各产品 layout 持有。
**HC-37**：五产品使用同一份 Bootstrap 5 与同一份图标字体（本地托管，禁 CDN）。

### 12.6 ERP 与 Shop 对参考项目对账（S11）

已核实（2026-11-27，脚本实测）：

- ERP 路由名：参考 353 / demo 355，**缺 0**。
- ERP 视图：参考 171 / demo 173，**缺 0**；行数无一项低于参考 75%。
- ERP app 代码：参考 20,602 行 / demo 40,492 行（demo 更多）。
- 结论：ERP **功能层已对齐**，用户感知的"没对齐"来自入口形态（参考 `/` 是工作台，demo 直接跳登录）与外框风格 → 由 12.3 / 12.5 解决。S11 只做两件事：把 ERP 首页工作台的菜单/卡片与参考 `home.blade.php` 对齐核查；跑对账脚本固化结论。

Shop 基线（`E:\erp-shop`）核对项：POS 收银、商品/分类/单位/税率、仓库/库位/批次/序列号、采购订单/收货/退货、销售退货、盘点/报损/调拨、财务（账龄/对账/结账/费用/报表/税务）、系统（审计日志/自动化/备份/支付通道）、会员/价格表/标签打印。

**HC-38**：新增 `tools/check-parity.php`，对 ERP、Shop 分别输出"路由名差异 / 视图差异 / 菜单项差异"三张表；差异必须为 0，或写入脚本内白名单并注明原因。

### 12.7 MES、HR 补齐后台管理（S12）

改造前现状：

- MES：`system/users`、`system/roles` 只有列表（仅能改角色与启停），无部门、无岗位。
- HR：`org/departments`、`org/positions` 有新增/编辑但岗位不能删，`system/users` 只有列表。

两产品各补齐一套（结构相同，代码与表各自独立，不共享）：

1. **账号管理**：列表（搜索 + 状态筛选）、新建、编辑、启停、改角色、删除。密码由操作者在表单里填写，新建必填、编辑留空即不改；页面与代码不预填任何口令（守 G-10），对外公布的演示口令只出现在产品首页的演示账号卡。
2. **部门管理**：列表（上级 / 账号数 / 岗位数）、新建、行内编辑（名称/上级/排序/启停）、删除。有下级或有账号（HR 为在职员工）时拒绝删除。
3. **岗位管理**：列表（所属部门 / 在岗人数）、新建、行内编辑、删除。有账号（HR 为在职员工）占用时拒绝删除。
4. **角色权限**：沿用既有权限矩阵页（MES/HR 各按自己的 guard 隔离），本版不重建。
5. **权限校验**：四个页面统一 `hasRole('admin')`，非管理员 403；判定写在方法内而非构造器，避免 `route:list` 与容器预解析被拦。
6. **菜单**：侧栏「系统管理」分组 = 部门管理 / 岗位管理 / 账号管理 / 角色权限 / 站内通知，非管理员整组隐藏。

数据结构增量（已实施）：

- MES 迁移 `2026_11_27_100004_create_mes_org_tables`：建 `departments(id, name, parent_id, sort, status, timestamps)`、`positions(id, name, department_id, status, timestamps)`；`users` 加 `department_id`、`position_id`（`username` 由 S9 迁移补）。
- MES 种子 `OrgSeeder`：7 部门（生产中心 / 品质部 / 设备部 / 计划部 / 信息部 + 一号车间 A 班 / SMT 车间）、5 岗位、5 个演示账号挂靠，幂等可重跑。
- HR：`departments`、`positions` 原本已有 `sort`/`status`，无需新迁移；本版补岗位删除与账号 CRUD。

**HC-39（修订）**：MES/HR 后台四项必须可增删改查并带权限校验；**不要求**操作审计落库——演示站不引入 `operation_logs`，避免为审计而审计的空表。审计属客户化交付项。
**HC-43**：MES/HR 的演示数据必须点得动——新建部门/岗位、新建账号、改角色与状态后，列表与 KPI 立即变化。

范围外（明确不做）：多租户、审批流配置器、组织架构图拖拽、自定义字段、操作审计表。

### 12.8 阶段重排（v4）

| 阶段 | 内容 | 依赖 | 交付物 |
|---|---|---|---|
| S8 | 门户文案精简 + 五产品对外首页 | 无 | 5 个 `index.blade.php` + 路由 + 门户改动 |
| S9 | 登录统一"邮箱或用户名" | S8 | 5 个登录控制器/表单 + 3 张迁移 + seeder 回填 |
| S10 | 五产品统一皮肤（PM 风格） | S8 | `shell.css` 注入 5 个 layout + 品牌变量 |
| S11 | ERP / Shop 对账与补齐 | S10 | `tools/check-parity.php` + 差异清单（0） |
| S12 | MES / HR 后台完备 | S10 | 两套 admin 四模块 + 迁移 + 种子 |
| S13 | 全量回归 + 上线 | S11、S12 | 门禁/走查/HTTP 全绿 + 线上同步 |

每个阶段收口动作固定为四条：本地门禁 → 走查脚本 → 提交推送 → 线上 `deploy.sh demo` + 抽查。

### 12.9 v4 验收口径

- [ ] 门户首页无"一个站点 / 5 套独立系统"文案；五卡点击进入各自产品首页（非登录页）。
- [ ] `/erp/`、`/pm/`、`/shop/`、`/mes/`、`/hr/` 免登录 200，已登录 302 到工作台。
- [ ] 五个首页均显示演示账号与 `123456`，页脚能回产品列表。
- [ ] 五产品登录：`admin` 与 `admin@*.demo` 均可登入；错误文案统一；限流生效。
- [ ] 五产品外框一致（侧栏/顶栏/内容区同一壳），品牌色区分；移动端抽屉可用。
- [ ] `tools/check-parity.php` ERP、Shop 差异为 0（或白名单）。
- [ ] MES/HR 后台 用户/部门/岗位/角色 四项 CRUD 可用，权限与审计生效。
- [ ] 回归：走查、交叉登录、人设、独立性、视觉、HTTP 六套脚本全绿；线上五登录 302→200。

### 12.10 目录增量（相对 v3）

```
resources/views/shared/
  landing.blade.php                             # 新增：五产品共用对外首页骨架
public/assets/shared/css/landing.css            # 新增：首页皮肤
public/assets/shared/css/shell.css              # 新增：五产品统一外框皮肤
app/Modules/{Erp,Pm,Shop,Mes,Hr}/Resources/views/landing.blade.php      # 新增：五产品对外首页
app/Modules/{Erp,Pm,Shop,Mes,Hr}/Http/Controllers/LandingController.php # 新增：首页入口
app/Modules/Mes/Models/{Department,Position}.php                        # 新增
app/Modules/Mes/Http/Controllers/System/{Department,Position}Controller.php  # 新增
app/Modules/Mes/Resources/views/system/{departments,positions}/         # 新增
app/Modules/Mes/Database/Migrations/*_create_mes_org_tables.php         # 新增
app/Modules/Mes/Database/Seeders/OrgSeeder.php                          # 新增
app/Modules/Hr/Resources/views/system/user-form.blade.php               # 新增
app/Modules/{Pm,Mes,Hr}/Database/Migrations/*_add_{email,username}_to_users.php  # 新增
tools/check-parity.php                          # 新增：参考项目对账
tools/smoke-{mes,hr}-system.ps1                 # 新增：后台 CRUD 冒烟
tools/smoke-online{,-login,-admin}.ps1          # 新增：线上验收
```

### 12.11 上线记录与两个必须记住的坑（2026-11-27）

部署方式：本地 `tar` 打包 → `scp` 到 `/tmp` → 服务器解包覆盖 → `bash deploy/deploy.sh demo`。

**坑 1（严重）：打包必须排除 `.env`。**
首次打包未排除 `.env`，线上 `.env` 被本地同名文件覆盖，`APP_KEY` 随之改变；ERP 库里用旧 key 加密的 `platform_instances.credentials_json` 解不开，seeder 在 `EcomDemoSeeder` 处抛出 `The MAC is invalid`。旧 `.env` 与旧站一起被删，密钥不可恢复。
处置：清空该加密列（1 条记录）后由 seeder 用当前 key 重建；其余加密列（`ecom_platforms` / `ai_providers` / `wecom_configs` / `users.mfa_secret` / Shop 支付密钥）线上均无密文，无需处理。
结论：**打包命令必须带 `--exclude '.env' --exclude '.env.*'`；`.env` 只能由服务器本地维护。**

**坑 2：`deploy.sh` 的全量 seed 不幂等。**
库已初始化时再跑 `db:seed DatabaseSeeder` 会在 `Pm\DepartmentSeeder`（`departments.code` 唯一键）撞 `UNIQUE constraint failed`。已改为：先数 `users` 表行数，>0 就跳过种子并提示"要重灌先清库"；增量数据用单独 seeder 跑（本次 MES 的 `OrgSeeder` 就是单独执行的）。

**坑 3：新加的登录列必须回填，否则"用户名登录"看似失效。**
S9 的三张迁移只加列不回填 —— 线上历史账号 `email`/`username` 为空，表现为"用邮箱登录被弹回登录页、用户名登录却正常"。回填规则：`email` 空 → `{username}@{产品}.demo`；`username` 空 → email 前缀（重名加序号）。回填后五产品 10 种组合（用户名/邮箱 × 5）全部落点正确、看板 200。

线上验收结果（`tools/smoke-online*.ps1`）：

- 门户 `/`：200，命中五张产品卡、邮箱、微信、备案位；不含"一个站点"、不含"电话"。
- 五个对外首页 `/erp/ /pm/ /shop/ /mes/ /hr/`：各 200，标题与产品一致。
- 五个登录页：都含「邮箱或用户名」。
- 登录 10 种组合：全部 302 → 对应看板 200。
- MES 后台：`system/departments`、`system/positions`、`system/users`、`users/create`、`system/roles` 全 200。
- HR 后台：`system/users`、`users/create`、`system/roles`、`org/departments`、`org/positions` 全 200。

### 12.12 三类定位落地：对齐审计 + MES/HR 完备补全（2026-11-27，v4 终版口径）

**产品定位（本节起为最终口径，后续不得再混）**

| 卡片 | 类型 | 参考基线 |
| --- | --- | --- |
| 电子厂 ERP | 对齐型 | `E:\ddwl-erp\erp` |
| 研发项目管理 PM | 对齐型 | `E:\my-pm\pm-server-openEuler` |
| 百货零售 ERP | 对齐型 | `E:\erp-shop` |
| 智能制造 MES | 新增型（独立完备） | 无参考目录，对标 Odoo Manufacturing / ERPNext |
| 人事管理 HR | 新增型（独立完备） | 无参考目录，对标 OrangeHRM / Odoo HR |

「协同办公」「电话会议」**不在对齐范围**（PM 基线里保留的部分视为参考项目自有功能，演示站不复刻）。

**12.12.1 对齐验收口径（三层对账）**

不看代码相似度，只看三件事能否对上：

1. **路由层**：HTTP 方法 + URI（去产品前缀、`{id}` 统一成 `{*}`）+ `Controller@method` 三维比对，输出缺失 / 多余 / 同路径换方法三类清单。
2. **数据层**：双方 migrations 的表集合与列集合比对，判「缺表 / 缺列」。
3. **视图层**：同名视图里 `route('x.y')` 引用集合比对（路由名去前缀归一化），引用缺失 60% 以上判为空壳。

脚本常驻 `tools/compare-{routes,schema,views}.php`，基线 JSON 与明细文本落在 `tools/baseline/`，任何时候可重跑复现。

**12.12.2 审计结论**

| 产品 | 路由（参考/演示） | 表（参考/演示） | 视图（参考/演示） | 差异归属 |
| --- | --- | --- | --- | --- |
| ERP | 353 / 355，缺 0 | 287 / 287，缺表 0 缺列 0 | 171 / 174，缺 0 空壳 0 | 仅根路由换成产品首页（S8 设计） |
| Shop | 235 / 224，缺 12 | 82 / 82，缺 0 缺列 0 | 117 / 117，缺 0 空壳 0 | 12 条全为 `/api/ai/*` 外部数据 API |
| PM | 268 / 196，缺 74 | 49 / 41，缺 8 | 102 / 97，缺 5 空壳 3 | api 38 + 企业微信 17 + 协同办公 11 + 注册 2 + 2 条 PATCH |

PM 那 2 条是形式差异：`/admin/departments/{id}`、`/admin/roles/{id}` 参考项目用单一 `AdminController@update` 同时接 `PATCH|PUT`，演示站拆成 `updateDepartment` / `updateRole` 且只注册 `PUT`；功能完全等价。

对外表述统一为：**三层对账下，三套对齐系统的差异全部集中在 AI 外部 API、企业微信、协同办公、电话会议、注册页这五类已知不开放项；除此之外 ERP 与 Shop 零差异，PM 仅剩两条 HTTP 方法的形式差异。**

**12.12.3 MES 补全（四块闭环）**

原 MES 有能力但没闭环：有工单、工序、报工、领料、质检、返工、设备点检报修、APS 与四张报表，**但报工之后没有下文**。补的是 Odoo「MO → Produce → Finished goods receipt」这一段：

- **完工入库**：`finished_receipts` 表 + `CompletionService`。可分批入库、超计划量直接拒绝、入库即累加成品 `materials.stock_qty`；入满计划量自动转 `completed`；关单要求所有工序 done，否则拦截并提示还差几道。
- **批次追溯**：`lots` 表 + `TraceController`。批次号 `LOT-{Ymd}-{seq}`，父批次自动取领料量最大的那个物料在生产批次之外的最新批次；`Lot` 用 `getRouteKeyName()=lot_no` 做路由绑定，追溯页可按批次看「上游原料 → 本批生产 → 下游入库」。
- **计划保养**：`maintenance_plans` / `maintenance_orders` + `MaintenanceService`。到期计划一键生成保养单；同计划已有未完成单则跳过，避免重复派单；完成后回写 `last_done_at` 与 `next_due_at`。
- **OEE**：`Reports\OeeController` 按工作中心聚合，A = 运行/(运行+停机)，P = 理论工时/运行工时（封顶 100%），Q = 合格/(合格+不良)。

菜单新增 4 项（现场组：完工入库、批次追溯；质量与设备组：保养计划；报表组：OEE），工单详情页加「完工入库（数量 + 批次号）」「关单」「已入库 x / 计划 y」。

实测：WO9 先入 200、再入 600，累计 800 = 计划量 → 自动 completed；WO17 关单成功；WO9 关单被 6 道未完成工序拦下；到期计划重复生成被正确跳过。

**12.12.4 HR 补全（四块）**

原 HR 已有员工档案、合同、异动、转正离职、组织岗位编制、考勤班次加班异常、请假与余额、薪资结构与批次发放、绩效、招聘、报表、导入、系统管理。对标 OrangeHRM 与 Odoo HR 后补四块：

- **培训管理**：课程库（编号/类别/课时/提供方）+ 培训场次（起止/地点/讲师/容量/状态）+ 报名与结果（pass / fail / pending + 分数），页面给通过率与下一期。
- **技能矩阵**：`skills`（分类 + 最高等级）× 员工，形成矩阵视图，可挂等级、可评估日期；证书单独成台账，60 天内到期高亮、已过期标红。
- **排班表**：周网格（人 × 日），支持「一批人 × 日期区间 × 一个班次」批量排班，按「人 + 日」唯一键 `updateOrCreate` 避免重复；点格子可在 planned → actual → leave → absent 之间切换。
- **员工自助**：`MeController` 按登录账号的 `employee_no` 关联员工档案，聚合本月考勤、假期余额、本周排班、培训记录、证书、最新工资条与合同；未绑定档案时给降级提示而不是报错。

数据 7 张表、5 个视图、4 个控制器、8 组路由、5 个菜单项；种子造了 6 门课 / 5 期场次 / 50 条记录 / 8 项技能 / 120 条员工技能 / 20 张证书 / 150 条本周排班。

**12.12.5 本轮第三个必须记住的坑：Blade 单行 `@php(...)` 里带命名空间会 500**

写法 `@php($received = (float) \App\Modules\Mes\Models\FinishedReceipt::query()->sum('qty'))` 会让 Blade 编译出 `PHP Parse error: Syntax error, unexpected T_NS_SEPARATOR`，表现为**整页 500 且日志只有编译错误**。12 张工单详情页同时 500、`git checkout` 还原视图立刻恢复 200，就是靠这个现象定位的。

正确做法：这类逻辑一律写成多行块

```
@php
    $receivedQty = (float) \App\Modules\Mes\Models\FinishedReceipt::query()->sum('qty');
@endphp
```

并且变量名要避开模板里已有的同名变量（本例原来叫 `$received`，改成 `$receivedQty` / `$remainQty`）。

**12.12.6 回归结果**

`guard.ps1` 17/0（G-13 全量 1583 个文件 `php -l` 通过）、视觉 45/0、独立性 31/0、人设 10/0、交叉登录 5/0、走查 17/0；`check-http.ps1` 新增 10 项固定清单（MES 5 + HR 5），项数 60 → 70，实测 **70/0**。

**12.12.7 上线（2026-11-27 16:50）**

`c7a971b` 打包（排除 `.env` 与 `database/*.sqlite`）→ `scp` → 服务器解包 → `deploy.sh demo`。两套新迁移在线上均为 `Ran`；库已有账号，种子按设计跳过，增量数据单独执行 `CompletionSeeder` 与 `TrainingSkillSeeder` 后：`lots=16 / plans=6 / courses=6 / roster=150`。线上 10 个新页面（MES 完工入库、保养计划、保养单、批次追溯、OEE；HR 培训、技能矩阵、证书台账、排班表、我的自助）全部 200。




### 12.13 对齐收口与两个"最后一公里"补全（2026-11-27 第二段）

**12.13.1 PM 的 2 条 PATCH 已补齐**

`/admin/departments/{id}`、`/admin/roles/{id}` 改为 `Route::match(['put', 'patch'], ...)`，与参考项目的 `PATCH|PUT` 一致。对账脚本同时修正了多方法路由的口径：把 `PUT|PATCH` 这类合成方法拆成单方法键再比对，否则参考项目分开注册的两条会被误判成缺失。

补齐后：**ERP 缺 0、Shop 缺 12（全 AI 外部 API）、PM 缺 72**。PM 的 72 条经拆解仍全部落在排除项：`api` 38、企业微信 17（`wecom-file` 7 + `admin/wecom` 4 + `profile/wecom` 3 + `wecom` 3）、协同办公 13（`project/*/collab|voice` 11 + `collab` 2）、电话会议 2、注册 2。因此 PM 的功能性缺口已清零，只剩"演示站不复刻的模块"。

**12.13.2 MES：SOP 工艺文件下发**

对标 Odoo 的 Work Instructions 与 ERPNext 的 Job Card 附件，新增 `sop_documents` 表（挂 `routing_operation_id`），页面按「工艺路线 → 工序」列出覆盖情况：已下发 / 草稿 / 作废 / 未下发，覆盖率与缺口计数在顶部 KPI。支持正文要点（无附件时现场直接看这段）、附件上传（PDF/图片/Word/Excel ≤10MB）、版本号升版、生效日期、按工序登记与删除；下载时无附件则把正文导出成 txt，保证现场一定有东西可拿。

演示数据：8 条工艺路线共 40 道工序，配 37 份 SOP（后 3 条路线各留 1 道未下发，用来演示"补登记"），其中 6 份带 .txt 附件。

实测：列表 / 切换路线 / 登记页 / 详情 / 下载均 200；登记写入成功（`SOP-RT-FG-006-10 vB/0`）；升版更新成功（`vC/0`）；附件上传落盘 `sop/*.txt` 且原名保留；删除清理干净。

**12.13.3 HR：年假额度按司龄自动核算**

对标 OrangeHRM 的 Leave → Entitlements。规则五档：不满 1 年 0 天、1–3 年 5 天、3–5 年 7 天、5–10 年 10 天、10 年以上 15 天；司龄按入职日算到核算年度的 12 月 31 日。

页面先给"差异预览"（每人应享 / 现额度 / 已休 / 差额 / 适用档），再一键发放：只写 `leave_balances` 的 `total` 与 `remaining`，`used` 保持不动，避免冲掉已休天数；没有余额记录的人自动补建。

实测：核算页 200（68 人需调整）；执行发放返回"调整 68 人、无变化 8 人、合计授予 468 天"；再核算一次"需调整 0 人"，即幂等。

**12.13.4 第四个必须记住的坑：`declare(strict_types=1)` + 表单字符串参数 = 500**

表单提交的所有字段都是字符串，控制器里若把 `$data['routing_operation_id']`（`'28'`）直接传给声明为 `int $id` 的私有方法，在 `strict_types=1` 下会抛 `TypeError` → **500**，而 tinker 里用整数 `28` 调同一个方法是成功的，容易误判为"数据/权限问题"。

症状特征：自定义 500 页（"系统开了小差"）替换了异常页，`storage/logs/laravel.log` 里查不到任何记录，只能靠逐步 `file_put_contents` 打点定位。

结论：**凡是从请求取到的数值参数，进方法前一律 `(int)` 显式转换**；排查 500 且日志空白时，先怀疑"自定义错误页吞掉了异常"，用打点或临时开 `APP_DEBUG`（事后必须改回 false，并把 `.env` 备份 move 回来）。

**12.13.5 回归**

`guard.ps1` 17/0、视觉 45/0、独立性 31/0、走查 17/0；`check-http.ps1` 固定清单再补 2 项（MES `/mes/sop`、HR `/hr/leaves/accrual`），项数 70 → 72。


**12.14 S16：五产品全面冒烟与三套对齐复核**

定位前提不变：`电子厂 ERP` ↔ `E:\ddwl-erp\erp`，`研发项目管理 PM` ↔ `E:\my-pm\pm-server-openEuler`，`百货零售 ERP` ↔ `E:\erp-shop`；`智能制造 MES`、`人事管理 HR` 为自建完备系统，无参考目录。

**12.14.1 冒烟方法**

新增 `tools/smoke-all.ps1`（本地）/`smoke-live.sh`（线上）：按产品登录演示 admin，用 `route:list --json` 取出全部**无参 GET** 页面（排除 login / api / ai / export / download / preview / 回调类），逐页请求并归类 —— `200` 通过、`403` 记 WARN（权限或业务拒绝，不是程序错误）、`302→登录页`、`404`、`500` 记失败。含 `{id}` 参数的详情页单独抽样验证。

**12.14.2 冒烟结果（本地与线上一致）**

| 产品 | 无参页通过 | 失败 | 业务拒绝(403) | 含参跳过 |
| --- | --- | --- | --- | --- |
| 电子厂 ERP | 131 | 0 | 0 | 38 |
| 研发项目管理 PM | 62 | 0 | 0 | 36 |
| 百货零售 ERP | 68 | 0 | 3 | 31 |
| 智能制造 MES | 34 | 0 | 0 | 13 |
| 人事管理 HR | 39 | 0 | 0 | 7 |
| 合计 | **334** | **0** | 3 | 125 |

那 3 个 403 是 POS 的挂单/取单、支付方式、交班汇总三个 AJAX 端点：演示管理员不是当前开班收银员（开班人是 `cashier@shop.demo`），`currentSession()` 按设计拒绝，属业务语义正确。

**12.14.3 本轮修掉的 6 类问题**

1. **ERP 侧栏一项都没有**（用户报的现象）：`config/navigation.php` 里 126 条菜单写的是参考项目的裸路由名（`home`、`office.todos`…），而演示站路由一律带 `erp.` 前缀，`Route::has('home')` 全 false → 28 项全被跳过。`NavigationService` 加 `resolveRouteName()` 做"裸名 → `erp.` 前缀"兜底后，admin 侧栏恢复 3 组 28 项、skipped=0。
2. **29 / 30 个 ERP 账号没有角色**：演示账号虽填了 `department_id` / `position_id`，但没挂岗位角色，登录后只剩"未绑定岗位"。新增 `PositionRoleSeeder` 按 `positions.role_names`（JSON）同步角色，无岗位的历史账号兜底综合岗；30 个账号全部有菜单。
3. **ERP 运维页 500**：`DevopsController` 无条件 `DB::select('SHOW TABLES')`，本地 SQLite 直接报错。改为按驱动分支（sqlite → `sqlite_master`、pgsql → `information_schema`、其余 → `SHOW TABLES`）。
4. **ERP BOM 详情 500**：视图里 `BomStatus::label($bom->status)` 是静态调用实例方法，改 `tryFrom()->label()`。参考项目此处写的是"状态类静态方法"，移植成 enum 后语义不兼容，属移植期埋的坑。
5. **ERP 业务详情页 404**：`system_admin` 岗位的 `data_scope_type = none`（"系统管理员不参与业务"），客户 / 供应商 / 采购单 / 生产单详情被行级范围过滤成 0 行。给 `username=admin` 叠加总经理岗（`data_scope=all`），演示管理员既做系统配置也能翻全部业务数据，侧栏 28 → 31 项。
6. **PM 8 个"我的任务"状态页 500 + 支援中心部门资料 500**：`TaskController::myTasksByStatus()` 里视图名写的是 `task.my-tasks-status`（缺 `pm::` 模块前缀，参考项目单模块无需前缀）；`SupportController::departmentFiles()` 引用的视图在参考项目里也不存在。前者补前缀，后者补建 `support/department-files.blade.php`（范围过滤 + 空态 + 下载）。

顺带修掉的两个小瑕疵：Shop 到货单 `create` 无参直开时 `findOrFail(0)` → 404，改为回采购单列表并提示；Shop 头像接口无头像时 404 导致破图，改为回落到内置默认头像，并把参考项目 `public/avatars/defaults` 的 5 张 SVG 补进演示站。

**12.14.4 三套对齐复核（三层对账重跑）**

| 维度 | ERP | PM | Shop |
| --- | --- | --- | --- |
| 路由缺失 | 0 | 72（全部落在排除项） | 12（全部 `/api/ai/*`） |
| 路由多出 | 2（演示站自建 `/home`、`/preview`） | 2 | 1（`/preview`） |
| 表 / 列缺失 | 0（287 表全齐） | 1 表（`users` 缺 4 个 `wecom_*` 列） | 0（82 表全齐） |
| 视图路由引用缺失 ≥60% | 0（148 视图齐） | 2（`profile/wecom/index`、`wecom/files/index`） | 0（98 视图齐） |

排除项口径未变：AI 外部 API、企业微信、协同办公、电话会议、注册页。把这些剔掉后，三套系统的**路由 / 表 / 视图引用三层均无功能缺口**；PM 那 72 条拆开是 api/ai 38 + 企微 17 + 协同 13 + 语音会议 6（含 hooks）+ 注册 2，Shop 的 12 条全为 ai。

**12.14.5 遗留**

- 含参详情页只做了抽样（每产品 12 条），未做全量 125 条自动巡检 —— 参数类型五花八门（`{lot_no}`、`{platformKey}`、`{slug}`），用固定 id 替换会造出假 404，暂不自动化。
- 线上 `.env` 本轮从 `APP_ENV=local / APP_DEBUG=true` 改回 `production / false`（此前排查遗留），`config:cache` 已重建。

**12.15 演示数据覆盖要求（验收口径）**

演示站的价值不在"页面能打开"，而在"每个岗位点进去都有东西看、每条单据链路都走得通"。因此把演示数据写成可验收的硬要求，而不是"尽量补一点"。

1. **覆盖对象**：五套系统（ERP / PM / Shop / MES / HR）的全部预置数据。种子按"模块 DatabaseSeeder + 覆盖种子"两段组织：基础种子保证系统可用，覆盖种子只负责补齐空列表与空工作台。
2. **用户覆盖岗位**：每个可见的岗位操作界面至少要有一个能登进去的演示账号；同一岗位有多个视角（执行人 / 审核人 / 管理员）时，每个视角各一个账号，不能出现"只有 admin 能看到"的页面。
   - ERP 30 个账号覆盖 37 个角色；PM 58 个账号覆盖 6 个角色；Shop 8 个账号覆盖 8 个角色（含收银员）；MES / HR 各 5 个账号覆盖系统管理与业务岗位。
3. **数据覆盖业务场景**：每一类业务单据至少一条"可演示链路"——列表页有行、详情页能进、状态能推进、审批能落一票。判定标准是"列表页 `<td>` 数 ≥ 1 且无 500"，而不是"表里有行"。
4. **允许的虚拟名**：数据库演示数据可以用虚构企业名（如"演示精密电子（深圳）有限公司"），但页面静态文案、菜单、标题、页脚一律不得出现真实公司名（见 12.16）。
5. **可复跑**：覆盖种子必须幂等，重复执行不产生重复主键冲突或脏数据；线上补数据只跑覆盖种子，不整库重置。
6. **回归手段**：`tools/check-empty-pages.ps1` 按产品扫全部 GET 页面，输出空列表清单；`tools/smoke-live.sh` 按产品登录后抽查关键页，输出 HTTP 码与 `<td>` 计数；两者组合作为"演示数据是否达标"的验收工具。
7. **当前基线**：ERP 空列表从 94 收敛到 25（剩余全是表单 / 个人页 / 仪表盘 / 图表页）；PM 38 → 24；Shop 20 → 19（剩余为 POS 收银台 / 个人页 / AI 页）；MES / HR 各 5 个且均为表单或树形页。线上冒烟 85 项全绿。

**12.16 后台管理边界（五系统统一）**

用户能感知的"后台乱"有两类：一类是后台侧栏混进前台业务入口，一类是后台缺该有的管理项。定稿口径如下。

1. **边界**：管理后台只做"系统本身"的事——用户、部门、岗位、角色、AI 导入引导、运维监控、系统设置；**不创建、不审批、不过账任何业务单据**，也不提供业务模块入口。
2. **统一入口**：五套系统一律在 `/{产品}/admin` 提供后台首页，侧栏固定 8 项：后台首页、用户管理、部门管理、岗位管理、角色管理、AI 导入引导、运维监控、系统设置；产品自己的扩展维护项（ERP 主数据 / 权限矩阵 / 可观测，PM 状态字典 / 任务类型 / 操作日志 / 服务备份，Shop 审计日志 / 自动化 / 备份，MES / HR 消息通知）单列"扩展维护"组。
3. **实现方式**：`resources/views/system/layout.blade.php` 一套皮肤 + `SystemConsoleController` 一个控制器，按产品做数据适配（`App\Support\System\SystemConsole` 探测各库的用户 / 部门 / 岗位 / 角色列名并归一化）。原模块后台视图统一 `@extends('system.layout')`，因此后台页面天然只有后台侧栏，不再出现前台菜单。
4. **组织表补齐**：Shop 新建 `departments` / `positions`（并在 `users` 上加 `department_id` / `position_id`）；PM 新建 `positions`（并在 `users` 上加 `position_id`），使五套系统的"部门 / 岗位"后台都有真实数据可管。
5. **权限**：后台入口只对 `admin` 账号或角色名含 `admin / super / sys` 的账号开放，其余账号 403，避免演示站被任意账号改组织数据。
6. **只读优先**：系统设置页只读展示产品配置与环境参数（改动走 `config/products.php` 与 `.env`）；运维监控页只读展示运行时、库大小、表行数、日志尾部，不在页面里做写操作。

**12.17 移动端适配口径**

1. **范围**：门户、五套系统前台、五套系统后台、五个登录页；收银台（POS）与打印类页面按"平板优先、手机横向滚动"处理，不追求手机端重排。
2. **断点**：991.98px（手机与竖屏平板），后台皮肤 900px；桌面端不受影响（适配层全部写在媒体查询内）。
3. **交互**：侧栏在窄屏改为抽屉——点顶栏汉堡（ERP / Shop）或右下角浮动按钮（MES / HR / 系统后台）滑出，点遮罩 / Esc / 点菜单项关闭，滚轮锁定背景。
4. **内容**：表格统一包滚动容器横向滑动，卡片与表单留白收紧，按钮与输入框触摸高度 ≥38px；MES / HR 顶栏在窄屏换行，避免挤成竖排。
5. **验收**：手机宽度下 `document.documentElement.scrollWidth` 等于视口宽（无横向溢出）；抽屉能开能关；桌面 1280 宽布局与改造前一致。

**12.18 PM 与参考目录严格对齐（覆盖 12.16 中"五系统统一后台"的口径）**

1. **优先级**：PM 的硬约束是"所有页面与 `E:\my-pm\pm-server-openEuler` 严格对齐"，优先于 12.16 的统一后台方案。因此 PM 不接入五系统统一后台皮肤。
2. **后台结构**：PM 后台只保留参考项目自身的 `/pm/admin`（`AdminController` + `admin/*` 视图，24 个页面），视图一律 `@extends('pm::layouts.app')`，侧栏与顶栏由 `layouts.app` 统一渲染，菜单项、分组、角色可见性均与参考一致。
3. **下线项**：PM 不再注册统一后台的额外页面（`/pm/admin/positions`、`ai-guide`、`ops`、`settings`），这些地址返回 404，避免出现参考目录里没有的菜单或页面。
4. **移动端**：适配层只注入 `responsive.css` / `responsive.js`（新增 link/script），不改动任何视图正文、菜单文案或菜单结构；移动端交互沿用 PM 自带 offcanvas。
5. **已知唯一差异**：参考目录的"企业微信 / 企业微信绑定 / 企微文件收件箱"三项依赖企业微信集成，演示站未部署该集成（无路由），因此 PM 侧栏缺这三项；如要补齐需先移植 wecom 模块或提供占位页。（2026-11-27 已按 12.19 完成移植，该差异已消除）
6. **验收**：PM 后台 11 个页面线上 200；侧栏项目与参考一一对应（首页、AI 设置/智能助手、AI 授权中心、用户/部门/角色、状态管理、任务类型、聊天记录、文件仓库、回收站、知识库、操作日志、发送通知、后台服务）；页面不再出现"系统管理后台 / AI 导入引导 / 返回门户"等统一后台元素。

**12.19 PM 企业微信模块移植（补齐 12.18 第 5 条的唯一差异）**

1. **范围**：把参考目录 `E:\my-pm\pm-server-openEuler` 的 wecom 模块整体移植进 `app/Modules/Pm`，落点与参考一一对应：
   - 控制器 5 个：`WecomAuthController`（OAuth 跳转/回调）、`WecomCallbackController`（回调事件与素材入库）、`WecomSettingsController`（集成配置/连通性测试/消息日志）、`UserWecomController`（个人绑定/解绑/通知偏好）、`WecomFileController`（企微文件收件箱）；
   - 模型 4 个：`WecomConfig`、`WecomFile`（软删除）、`WecomMessageLog`、`WecomOauthState`，均显式 `$connection = 'pm'`；
   - 服务 2 个：`WecomService`（access_token、发消息、OAuth、素材下载、回调验签）、`WecomNotificationService`；
   - 通知通道 `Notifications/Channels/WecomChannel`；配置 `config/wecom.php`；`config/filesystems.php` 增 `wecom_files` 本地盘（`storage/app/wecom-files`，不对外暴露）；
   - 视图 3 个 + 消息日志页：`admin/wecom/settings`、`profile/wecom/index`、`wecom/files/index`、`admin/wecom/message-logs`；全部 `@extends('pm::layouts.app')`。
2. **路由**（与参考路径同名同义，统一加 `/pm` 前缀与 `pm.` 名字空间）：
   - 公开：`GET /pm/wecom/auth`、`GET /pm/wecom/callback`、`POST /pm/wecom/callback/event`；
   - 管理员：`GET|POST /pm/admin/wecom/settings`、`GET /pm/admin/wecom/test`、`GET /pm/admin/wecom/message-logs`；
   - 个人：`GET /pm/profile/wecom`、`POST /pm/profile/wecom/unbind`、`POST /pm/profile/wecom/settings`；
   - 文件：`GET /pm/wecom-file`、`/{file}/preview|download`、`POST /{file}/archive`、`DELETE /{file}`、`POST /send-to-wecom`、`GET /api/bound-users`。
3. **菜单**（`pm::layouts.app`，位置与参考一致）：管理员组"后台服务"之后加"企业微信"；各角色"通知中心"之后加"企业微信绑定""企微文件收件箱"；移动端 offcanvas 同步补齐。
4. **数据**：5 个迁移在 `app/Modules/Pm/Database/Migrations`，表名与参考同名（`wecom_configs` / `wecom_message_logs` / `wecom_oauth_states` / `wecom_files`），`users` 表补 `wecom_userid` / `wecom_bound_at` / `wecom_notify_enabled` / `wecom_notify_settings`（均带 `hasColumn` 守卫，可重复执行）。
5. **未配置态**：演示站无企业微信凭据，配置页与个人绑定页显示"未配置"提示；测试连接会如实返回失败原因，不伪造成功。
6. **两处与参考的受控差异**（参考自身缺陷，照搬会 500 或影响全站）：
   - 参考 `WecomSettingsController::messageLogs()` 指向不存在的视图 `admin.wecom.message-logs`；演示站补建了同结构日志页（表格 + 分页 + 空态提示），路由保持与参考一致。
   - 参考未见 `wecom` 通知通道注册，而演示站全站通知走 `database` 通道；为避免未配置企微的环境在发通知时抛 `Driver [wecom] not supported`，`pm\Notifications\BaseNotification::via()` 暂不追加 `wecom`，通道类已就位，具备凭据后可一行开启。
7. **验收**：本地 `/pm/admin/wecom/settings`、`/pm/admin/wecom/message-logs`、`/pm/profile/wecom`、`/pm/wecom-file` 均 200；配置页回调地址渲染为 `/pm/wecom/callback/event`；后台侧栏出现"企业微信"，角色侧栏出现"企业微信绑定""企微文件收件箱"；线上冒烟补充 4 条 PM 企微页面。
