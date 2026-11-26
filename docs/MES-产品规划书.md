# MES 产品规划书（智能制造执行系统）

> 本文件是 MES 作为**独立第五产品**的完整设计，不是 ERP 子模块的搬运。
> 代码根：`E:\demo-website\app\Modules\Mes`。数据库：`database/mes.sqlite`（独立）。
> 前置：主规划书 v3 的 HC-01~HC-34 全部适用；本文件只写 MES 特有内容。
> 配套：`MES-任务清单.md`（M0~M6 共 70 项 / 50d）。

---

## 第 0 章｜定位与边界

### 0.1 一句话定位

**给车间用的系统**：工单从计划来，工人扫码报工，每道工序留痕，质检卡住不良，看板看得见进度。

### 0.2 与客户现有 ERP 的关系（这是 MES 的销售前提）

MES 的目标客户是**已经有其他厂商 ERP 的企业**。因此它与 ERP 的关系不是"竞争"，而是"补位"：

| 层 | 客户的现有 ERP（金蝶/用友/管家婆/我的 ERP…） | MES（`/mes`） |
|---|---|---|
| 管什么 | 计划与账：销售、采购、库存、成本、财务 | 车间执行：谁在做、做到哪、做了多少、好不好 |
| 谁用 | 计划员、采购、财务 | 车间主管、操作工、质检员、设备员 |
| 数据粒度 | 订单级 | 工序级、批次级、人机料法环 |
| 上线代价 | — | **不动客户现有系统**，独立部署、独立账号 |

**硬边界（MC-03）**：演示版 MES 与任何 ERP **零数据交互**——不调接口、不读外部库。数据进入 MES 的唯一通道是**Excel 导入**（§0.4）。
> 这条边界不是技术洁癖，而是销售需要：只有"不依赖任何 ERP 也能跑"，才能卖给"已经有 ERP"的客户。

### 0.3 MVP 边界（演示版做什么、不做什么）

| 做（必须有，否则演示不成立） | 不做（客户看不到，也不影响观感） |
|---|---|
| 物料/BOM/工艺路线/工序/工作中心/班次 | 与 ERP 的接口对接、主数据同步 |
| 工单创建/下达/派工/进度 | 复杂 APS 算法（用简版贪心排产 + 甘特图） |
| 报工（数量/工时/不良）、领料/退料 | 设备联网采集（PLC/SCADA/IoT） |
| 检验模板、IQC/IPQC/OQC、不良记录、返工 | SPC 统计过程控制、MSA |
| 设备台账、点检、报修 | OEE 精细化计算（给简化公式即可） |
| 车间看板、产量/良率/工时报表 | 多工厂、多组织、多语言 |
| 用户/角色/权限/通知 | 与 ERP 的财务凭证联动 |

> 原则：**每个页面都要有真实数据、每张表都要被至少一个页面用到**。演示站最怕"点进去是空页面"。

### 0.4 可对接性设计：Excel 导入 / 导出（MES 的成交关键）

**为什么必须做**：客户已经有 ERP，最怕的就是"换系统要重新录一遍基础数据"。能"从现有系统导出 → 导进 MES"，成交阻力会小一个量级。这条能力是 §0.2 话术的落地支撑，**不是可选项**。

| 方向 | 内容 | 演示时的说法 |
|---|---|---|
| 导入 | **物料清单**（编码/名称/规格/单位/分类） | "您从金蝶导出的物料表，按这个模板整理就能导进来" |
| 导入 | **BOM**（成品编码/子件编码/用量/损耗率） | "BOM 也能导，不用一条条录" |
| 导入 | **工单**（成品/数量/交期） | "ERP 里下的工单，导出后直接导入，车间就能开工" |
| 导出 | **报工汇总**（工单/工单号/良品/不良/工时） | "车间做完的数据导出来，您可以回填到 ERP 做成本核算" |
| 导出 | **不良明细** | "质量数据也能给您导出" |

**实现口径（演示版，控制在 2d 内）**：

| 决策 | 选择 | 理由 |
|---|---|---|
| 模板 | **固定模板**，不做字段映射向导 | 字段映射是正式版能力，演示版做会拖一周且客户看不出差别 |
| 库 | 复用 `maatwebsite/excel ^3.1`（依赖里已有） | 零新增依赖 |
| 流程 | 下载模板 → 上传 → **预览校验**（显示前 10 行 + 错误行） → 确认入库 | 预览这一步最有说服力："您看，导进来 40 条，第 37 行单位不对，我标红了" |
| 页面 | `/mes/import`（`mes.import.index`）导入中心，四个卡片 | 一个页面装下全部导入导出 |
| 错误处理 | 逐行校验 + 错误行下载 | 演示时可以故意导一份有错的，展示纠错能力 |

**页面与路由**（并入 MES 页面清单，总数 31 → 32）：

```
/mes/import        mes.import.index      导入中心（四个卡片）
/mes/import/template/{type}   mes.import.template   下载模板
/mes/import/{type}            mes.import.store       上传并预览
/mes/import/{type}/confirm     mes.import.confirm     确认入库
/mes/export/{type}            mes.export            导出（报工/不良）
```

> 同一套设计**复用到 HR**（导入员工花名册、导入考勤打卡记录）——这也是 HR 卖给"已有 ERP"客户时的必备能力，见 HR 规划书 §0.4。

---

## 第 1 章｜功能域与页面清单

### 1.1 六个功能域

| 域 | 名称 | 页面数 |
|---|---|---|
| D1 | 基础数据（物料/BOM/工艺/工作中心/班次） | 6 |
| D2 | 工单与排产 | 5 |
| D3 | 生产执行（派工/报工/领料） | 5 |
| D4 | 质量管理 | 5 |
| D5 | 设备管理 | 3 |
| D6 | 看板与报表 | 4 |
| D7 | 系统（用户/角色/通知） | 3 |

### 1.2 页面清单（共 31 个页面）

| # | 页面 | 路由 | 说明 |
|---|---|---|---|
| 1 | 车间看板 | `mes.kanban` | 大字号卡片：今日计划/已完成/在制/良率/异常；自动刷新 |
| 2 | 工作台 | `mes.dashboard` | 待办（待派工/待报工/待检验）+ 快捷入口 |
| 3 | 物料列表 | `mes.master.materials.index` | 编码 `MJ-xxxx`、名称、规格、单位、库存 |
| 4 | 物料编辑 | `mes.master.materials.edit` | 新增/编辑弹窗 |
| 5 | BOM 列表 | `mes.master.boms.index` | 成品 → 子件清单（用量/损耗率） |
| 6 | BOM 编辑 | `mes.master.boms.edit` | 增删子件行 |
| 7 | 工艺路线 | `mes.master.routings.index` | 成品 → 工序序列（工序号/工作中心/标准工时） |
| 8 | 工作中心 | `mes.master.work-centers.index` | 产线/工位、日产能、所属车间 |
| 9 | 班次 | `mes.master.shifts.index` | 白班/夜班、上下班时间 |
| 10 | 工单列表 | `mes.work-orders.index` | 工单号 `WO-2026xxxx`、成品、数量、状态、进度条 |
| 11 | 工单详情 | `mes.work-orders.show` | 工序进度、报工记录、领料记录、检验记录 |
| 12 | 工单创建 | `mes.work-orders.create` | 选成品+BOM+数量 → 自动带出工序与物料需求 |
| 13 | 工单下达 | `mes.work-orders.release` | 状态转为"已下达"，可派工 |
| 14 | 派工 | `mes.work-orders.dispatch` | 把工序派给班组/人 + 计划开始结束 |
| 15 | 报工录入 | `mes.work-orders.report` | 操作工录入：良品数/不良数/工时 |
| 16 | 报工记录 | `mes.reports.index` | 全部报工流水，可筛选工序/人/日期 |
| 17 | 领料单 | `mes.issues.index` | 工单 → 领料明细，含已发/未发 |
| 18 | 领料发料 | `mes.issues.store` | 按 BOM 需求自动生成行，逐行发料 |
| 19 | 检验模板 | `mes.quality.templates.index` | 按工序定义检验项（尺寸/外观/功能） |
| 20 | 检验单列表 | `mes.quality.inspections.index` | 类型（IQC/IPQC/OQC）、工单、结果 |
| 21 | 检验单执行 | `mes.quality.inspections.show` | 逐项录入实测值 → 自动判合格/不合格 |
| 22 | 不良记录 | `mes.quality.defects.index` | 不良代码、数量、工序、责任、处置（返工/报废） |
| 23 | 返工单 | `mes.quality.reworks.index` | 不良 → 返工任务，关联原工单 |
| 24 | 设备台账 | `mes.equipment.index` | 设备编号、名称、位置、状态、保养周期 |
| 25 | 设备点检 | `mes.equipment.checks.index` | 每日点检项与结果 |
| 26 | 设备报修 | `mes.equipment.repairs.index` | 报修单、故障描述、处理人、耗时 |
| 27 | APS 排产 | `mes.aps.index` | 甘特图：工作中心 × 时间，拖拽调序（简化版） |
| 28 | 产量报表 | `mes.reports.output` | 按日/产线/成品统计 |
| 29 | 良率报表 | `mes.reports.yield` | 良率趋势、不良 Top 5 |
| 30 | 工时报表 | `mes.reports.labor` | 按人/工序统计工时与效率 |
| 31 | 系统（用户/角色/通知） | `mes.system.*` | 三个页面合并 |

---

## 第 2 章｜数据模型（22 张表）

### 2.1 表清单

| # | 表名 | 说明 | 关键字段 |
|---|---|---|---|
| 1 | `users` | 账号 | `name`, `email`, `password`, `employee_no`, `team`, `status` |
| 2-6 | `roles`/`permissions`/`model_has_roles`/`role_has_permissions`/`model_has_permissions` | spatie 8.x | 标准结构 |
| 7 | `materials` | 物料 | `code`(`MJ-`), `name`, `spec`, `unit`, `category`, `safety_stock`, `stock_qty` |
| 8 | `boms` | BOM 头 | `code`, `product_material_id`, `version`, `status` |
| 9 | `bom_lines` | BOM 行 | `bom_id`, `component_material_id`, `qty_per`, `loss_rate`, `seq` |
| 10 | `routings` | 工艺路线 | `code`, `product_material_id`, `version`, `status` |
| 11 | `routing_operations` | 工序 | `routing_id`, `op_no`, `op_name`, `work_center_id`, `std_minutes`, `inspection_required` |
| 12 | `work_centers` | 工作中心/产线 | `code`, `name`, `workshop`, `daily_capacity`, `status` |
| 13 | `shifts` | 班次 | `code`, `name`, `start_time`, `end_time`, `cross_day` |
| 14 | `work_orders` | 工单 | `order_no`(`WO-`), `product_material_id`, `bom_id`, `routing_id`, `plan_qty`, `done_qty`, `defect_qty`, `status`, `plan_start`, `plan_end`, `priority` |
| 15 | `work_order_operations` | 工单工序 | `work_order_id`, `op_no`, `op_name`, `work_center_id`, `plan_qty`, `done_qty`, `defect_qty`, `status`, `assignee_id`, `team` |
| 16 | `production_reports` | 报工流水 | `work_order_id`, `operation_id`, `user_id`, `shift_id`, `ok_qty`, `defect_qty`, `minutes`, `reported_at` |
| 17 | `material_issues` | 领料单 | `issue_no`(`MI-`), `work_order_id`, `status`, `issued_at`, `issued_by` |
| 18 | `material_issue_lines` | 领料行 | `issue_id`, `material_id`, `required_qty`, `issued_qty`, `status` |
| 19 | `inspection_templates` | 检验模板 | `code`, `name`, `op_no`, `type`(`IQC/IPQC/OQC`), `items`(json) |
| 20 | `inspections` | 检验单 | `inspection_no`(`QC-`), `work_order_id`, `operation_id`, `template_id`, `type`, `result`(`pass/fail`), `inspector_id`, `inspected_at` |
| 21 | `inspection_results` | 检验明细 | `inspection_id`, `item_name`, `std_value`, `actual_value`, `verdict` |
| 22 | `defects` | 不良记录 | `work_order_id`, `operation_id`, `defect_code`, `qty`, `disposal`(`rework/scrap`), `cause`, `owner_id`, `status` |
| 23 | `equipments` | 设备 | `code`, `name`, `work_center_id`, `status`, `check_cycle_days`, `last_check_at` |
| 24 | `equipment_checks` | 点检 | `equipment_id`, `checked_at`, `checker_id`, `result`, `remark` |
| 25 | `equipment_repairs` | 报修 | `equipments_id`, `fault_desc`, `reported_by`, `handler_id`, `down_minutes`, `status` |
| 26 | `notifications` | 通知 | 标准 Laravel 结构 |
| 27 | `aps_schedules` | 排产结果 | `work_order_id`, `operation_id`, `work_center_id`, `start_at`, `end_at`, `seq` |

> 实际 27 行（含 spatie 5 张与 notifications），业务表 **21 张**。

### 2.2 三条数据硬约束

| 编号 | 约束 | 原因 |
|---|---|---|
| **MC-01** | 表名**不加** `mes_` 前缀（`work_orders` 而非 `mes_work_orders`） | 独立库，前缀无意义且增加改名成本 |
| **MC-02** | 物料编码统一 `MJ-` 前缀；工单号 `WO-`；领料单 `MI-`；检验单 `QC-` | 与 ERP 的 `MAT-` 明确区分（HC-32） |
| **MC-03** | 不得引用 ERP 的任何类、表、连接、接口 | 独立性是演示卖点 |

### 2.3 状态机（演示版必须做对，否则流程走不通）

| 对象 | 状态流转 |
|---|---|
| 工单 | `draft` → `released` → `in_progress` → `completed` → `closed` |
| 工单工序 | `pending` → `dispatched` → `in_progress` → `done` |
| 领料单 | `draft` → `issued`（部分发料时 `partial`） |
| 检验单 | `pending` → `passed` / `failed` |
| 不良记录 | `open` → `rework` / `scrap` → `closed` |
| 设备 | `running` / `idle` / `repairing` / `scrapped` |

---

## 第 3 章｜目录结构与命名空间

```
app\Modules\Mes\
├── BaseModel.php                      $connection = 'mes'
├── Http\
│   ├── Controllers\
│   │   ├── Auth\LoginController.php
│   │   ├── DashboardController.php
│   │   ├── KanbanController.php
│   │   ├── MasterData\{MaterialController, BomController, RoutingController, WorkCenterController, ShiftController}.php
│   │   ├── WorkOrderController.php           （列表/详情/创建/下达/派工）
│   │   ├── WorkOrderReportController.php     （报工）
│   │   ├── MaterialIssueController.php
│   │   ├── Quality\{InspectionController, InspectionTemplateController, DefectController, ReworkController}.php
│   │   ├── EquipmentController.php           （台账/点检/报修，三个方法组）
│   │   ├── ApsController.php
│   │   ├── Reports\{OutputController, YieldController, LaborController}.php
│   │   └── System\{UserController, RoleController, NotificationController}.php
│   ├── Requests\                      约 12 个（工单/报工/检验/领料）
│   └── Middleware\
├── Models\                            21 个模型，全部 extends BaseModel
├── Services\
│   ├── WorkOrderService.php           工单生成（按 BOM 展开物料需求、按工艺展开工序）
│   ├── DispatchService.php            派工
│   ├── ReportService.php              报工（累计良品/不良、推进工序状态）
│   ├── IssueService.php               领料（按 BOM 算需求、发料扣减）
│   ├── InspectionService.php          检验判定
│   ├── ApsService.php                 简版排产（按工作中心产能贪心排）
│   └── KanbanService.php              看板聚合
├── Providers\MesServiceProvider.php
├── Routes\web.php                     prefix('mes') + name('mes.')
├── Database\
│   ├── Migrations\                    约 10 个迁移文件（按域合并，避免 21 个碎文件）
│   └── Seeders\                       见 §6
└── Resources\
    ├── views\                         31 个页面 + layouts
    ├── lang\
    └── assets\mes.css + logo + shots
```

**命名空间**：`App\Modules\Mes\{Layer}\{Class}`。路由名全部 `mes.*`，URL 全部 `/mes/*`。
**视图命名空间**：`mes::`（`loadViewsFrom` 注册）。

---

## 第 4 章｜路由设计

### 4.1 路由组

| 组 | 前缀 | 中间件 | 说明 |
|---|---|---|---|
| 认证 | `/mes/login` | `guest:mes` | 独立登录页 |
| 公开 | `/mes/preview` | 无 | 产品介绍页（来自 portal） |
| 业务 | `/mes/*` | `web` + `module:mes` + `product.only:mes` + `auth:mes` | 全部业务路由 |
| 根 | `/mes` | `web` | 未登录→preview，已登录→dashboard |

### 4.2 完整路由表（约 40 条）

```
/mes/dashboard                          mes.dashboard
/mes/kanban                             mes.kanban

/mes/master/materials                   mes.master.materials.index / store / edit / update / destroy
/mes/master/boms                        mes.master.boms.index / store / edit / update
/mes/master/routings                    mes.master.routings.index / store / edit / update
/mes/master/work-centers                mes.master.work-centers.index / store / edit
/mes/master/shifts                      mes.master.shifts.index / store / edit

/mes/work-orders                        mes.work-orders.index
/mes/work-orders/create                 mes.work-orders.create
/mes/work-orders                        mes.work-orders.store
/mes/work-orders/{order}                mes.work-orders.show
/mes/work-orders/{order}/release        mes.work-orders.release
/mes/work-orders/{order}/dispatch       mes.work-orders.dispatch
/mes/work-orders/{order}/report         mes.work-orders.report

/mes/reports                            mes.reports.index

/mes/issues                             mes.issues.index
/mes/issues/{issue}/issue               mes.issues.store

/mes/quality/templates                  mes.quality.templates.index / store / edit
/mes/quality/inspections                mes.quality.inspections.index / show
/mes/quality/inspections/{inspection}/submit   mes.quality.inspections.submit
/mes/quality/defects                    mes.quality.defects.index / store
/mes/quality/reworks                    mes.quality.reworks.index / store

/mes/equipment                          mes.equipment.index / store / edit
/mes/equipment/checks                   mes.equipment.checks.index / store
/mes/equipment/repairs                  mes.equipment.repairs.index / store

/mes/aps                                mes.aps.index
/mes/aps/schedule                       mes.aps.schedule

/mes/reports/output                     mes.reports.output
/mes/reports/yield                      mes.reports.yield
/mes/reports/labor                      mes.reports.labor

/mes/system/users                       mes.system.users.index / store / edit
/mes/system/roles                       mes.system.roles.index / store
/mes/system/notifications               mes.system.notifications.index
```

> 注意：`mes.reports.index`（报工流水）与 `mes.reports.output`（产量报表）共用 `reports` 前缀但语义不同，命名已区分。

---

## 第 5 章｜权限与角色（spatie 8.x）

### 5.1 五个角色

| 角色 | code | 可见功能 | 演示用途 |
|---|---|---|---|
| 管理员 | `admin` | 全部 | 展示"功能很全" |
| 计划员 | `planner` | 基础数据、工单创建/下达、APS 排产、报表 | 演示"计划下工单" |
| 车间主管 | `supervisor` | 工单、派工、报工审核、看板、报表 | 演示"派工与盯进度" |
| 操作工 | `operator` | 我的任务、报工、领料申请 | 演示"扫码报工" |
| 质检员 | `qc` | 检验单、不良记录、返工 | 演示"卡不良" |

### 5.2 权限命名

`{域}.{对象}.{动作}`，例：`work_order.create`、`quality.inspection.submit`、`master.material.manage`。
每个角色只授予必需权限，**演示时切换角色能看到菜单变化**——这是"演示权限体系"的卖点。

### 5.3 五个演示账号（密码统一 `123456`）

| 账号 | 角色 | 演示场景 |
|---|---|---|
| `admin@mes.demo` | 管理员 | 全功能走查 |
| `planner@mes.demo` | 计划员 | 创建工单、排产 |
| `supervisor@mes.demo` | 车间主管 | 派工、看板 |
| `operator@mes.demo` | 操作工 | 报工（手机端体验） |
| `qc@mes.demo` | 质检员 | 检验判定 |

---

## 第 6 章｜演示数据（精工精密制造 · 苏州）

### 6.1 公司人设

| 项 | 值 |
|---|---|
| 公司名 | 精工精密制造（苏州）有限公司 |
| 行业 | 精密五金/CNC 加工 |
| 车间 | 2 个（机加车间、装配车间） |
| 工作中心 | 4 条产线（CNC-01/02、装配-01/02） |
| 员工 | 60 人（其中班组长 6 人、质检 5 人） |
| 班次 | 白班 08:00–20:00、夜班 20:00–08:00 |
| 编码 | 物料 `MJ-0001`…；工单 `WO-202611-0001`…；领料 `MI-`；检验 `QC-` |

### 6.2 种子数据规模

| 对象 | 数量 | 说明 |
|---|---|---|
| 物料 | 40 个 | 原料 15 / 半成品 10 / 成品 15 |
| BOM | 8 个成品 | 每个 3–6 个子件 |
| 工艺路线 | 8 条 | 每条 3–5 道工序 |
| 工作中心 | 4 个 | 含日产能 |
| 工单 | 20 张 | 状态覆盖：草稿 3 / 已下达 5 / 进行中 8 / 完成 4 |
| 工单工序 | 约 80 条 | 按工艺展开 |
| 报工记录 | 120 条 | 覆盖近 7 天，含不良 |
| 领料单 | 12 张 | 部分已发、部分待发 |
| 检验模板 | 6 个 | IQC/IPQC/OQC 各 2 |
| 检验单 | 25 张 | 含 3 张不合格 |
| 不良记录 | 15 条 | 5 种不良代码 |
| 返工单 | 4 张 | 关联不良 |
| 设备 | 12 台 | 含 2 台维修中 |
| 点检记录 | 60 条 | 近 5 天 |
| 报修单 | 5 张 | 含 1 张未闭环 |
| 排产结果 | 20 条 | 覆盖未来 5 天 |

> **关键**：数据要"能讲出故事"。20 张工单里要有一张"卡在质检"的、一张"缺料停线"的、一张"良率偏低"的——客户演示时才有东西可讲。

### 6.3 与 ERP 的隔离校验

| 检查 | 期望 |
|---|---|
| 物料编码 | MES 全 `MJ-`，ERP 全 `MAT-`，无交集 |
| 公司名 | MES 是"精工精密制造"，ERP 是"东莞恒创电子" |
| 人名 | 两套完全不同（MES 用"王建国/李秀兰"等，ERP 用"张三/李四"等） |
| 单号 | `WO-202611-` vs ERP 的销售单号规则不同 |

---

## 第 7 章｜演示主链路（客户 5 分钟走查脚本）

这是 S7 验收的核心，也是"演示站能不能卖货"的关键。按顺序走完，客户能理解 MES 在干什么：

| 步 | 操作 | 客户看到的 | 时长 |
|---|---|---|---|
| 1 | 打开 `/mes/kanban` | 大屏看板：今日计划 20 / 已完成 8 / 在制 7 / 良率 96.2% / 异常 2 | 30s |
| 2 | 点"在制工单"→ `WO-202611-0008` 详情 | 工序进度条：下料 100% → CNC 60% → 去毛刺 0% → 全检 0% | 40s |
| 3 | 主管账号 → 派工 | 把"去毛刺"工序派给李秀兰，设定 14:00–17:00 | 40s |
| 4 | 切操作工账号 → 报工 | 输入良品 120 / 不良 3 / 工时 2.5h → 进度条前进 | 60s |
| 5 | 切质检员 → 检验单 | 3 个尺寸项录入实测值，超差一项 → 自动判不合格 → 生成不良记录 | 60s |
| 6 | 不良 → 返工单 | 3 件转返工，挂回原工单 | 30s |
| 7 | 回看板 | 良率从 96.2% 降到 95.8%，异常 +1 | 20s |
| 8 | 打开 APS 排产 | 甘特图：4 条产线 × 5 天，工单块可拖拽 | 60s |
| 9 | 打开报表 | 产量趋势 / 不良 Top5 / 工时效率 | 40s |

> **每一步都要能"看到数字变化"**，这是演示最有说服力的部分。

---

## 第 8 章｜MES 特有硬约束（MC 编号）

| 编号 | 规则 | 检查 |
|---|---|---|
| MC-01 | 表名不加 `mes_` 前缀 | 表名扫描 |
| MC-02 | 编码前缀 `MJ-`/`WO-`/`MI-`/`QC-` | 种子数据抽查 |
| MC-03 | 不引用 ERP 任何类/表/连接/接口 | 静态扫描 `App\Modules\Erp`、`connection('erp')` |
| MC-04 | 不复用 ERP 的类名设计（`ProductionOrder`/`HrEmployee` 等不出现） | 类名比对 |
| MC-05 | 不注册任何定时任务 | `Schedule::` 0 命中 |
| MC-06 | 每个业务表至少被一个页面读写（不留死表） | 表-页面映射核对 |
| MC-07 | 状态机流转必须全部可走通（不允许"某状态无入口"） | 逐状态手工验证 |
| MC-08 | 操作工页面必须手机可用（车间用平板/手机） | 手机宽度走查 |

---

## 第 9 章｜验收标准

| 维度 | 标准 |
|---|---|
| 独立登录 | `/mes/login` 独立页；MES 账号在 ERP 登录无效 |
| 独立数据 | `mes.sqlite` 与 `erp.sqlite` 无任何交集表、无外键跨库 |
| 主链路 | §7 九步全部走通，且每步有数字变化 |
| 状态机 | 六组状态流转全部可走通 |
| 权限 | 五个角色切换后菜单/按钮可见性正确变化 |
| 移动端 | 报工页在 375px 宽可用 |
| 看板 | 数据准确（与报工记录、工单累计一致） |
| 视觉 | 独立 layout、独立主题色（建议深蓝+橙，区别于 ERP 的绿）、独立 logo |
| 门禁 | G-01~G-17 + MC-01~MC-08 全绿 |
| 隔离 | 与 ERP 的物料编码/公司名/人名无串（§6.3） |

---

## 附｜与 ERP 内置生产模块的差异（客户答疑用）

| 能力 | ERP 内置（`/erp` 的 production.*） | MES 产品（`/mes`） |
|---|---|---|
| 工单 | 有（列表+详情+下达） | 有，且展开到工序级 |
| 报工 | 有（按工单整体报） | 有（**按工序、按人、按班次**报） |
| 质检 | 有（简单检验记录） | 有（**模板化、IQC/IPQC/OQC 分类、逐项判定**） |
| 领料 | 有（按工单领料） | 有，按 BOM 自动算需求 |
| 排产 | 有（APS 页） | 有，且带甘特图 |
| 设备 | 无 | **有**（台账/点检/报修） |
| 看板 | 无 | **有**（大屏看板） |
| 追溯 | 弱 | 强（工序→人→批次） |

> 一句话话术：**"ERP 是给办公室算账的，MES 是给车间干活的。同一张工单，两边看法不同。"**
