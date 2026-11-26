# MES 任务清单（M0~M6 · 70 项 · 50d）

> 配套：`MES-产品规划书.md`。前置：主项目里程碑 A（`S4-live3`）已完成，`Modules/Mes` 预留位存在。
> 工时单位 `d`（半天 0.5d）。每阶段末必须门禁全绿（G-01~G-17 + MC-01~MC-08）。
> v2 增补：M1 增加"Excel 导入导出"4 项（+2d），支撑"客户已有 ERP"的成交场景（MES 规划书 §0.4）。

---

## 总览

| 阶段 | 目标 | 任务数 | 工时 |
|---|---|---|---|
| M0 | 基础设施 | 10 | 5.5d |
| M1 | 基础数据 + 导入导出 | 16 | 11d |
| M2 | 工单与排产 | 12 | 9d |
| M3 | 生产执行 | 10 | 7d |
| M4 | 质量管理 | 10 | 7d |
| M5 | 设备管理 | 6 | 4d |
| M6 | 看板/报表/系统/收尾 | 8 | 5d |
| **合计** | | **70** | **49.5d（按 50d 排）** |

**依赖**：M0 → M1 → M2 →（M3 / M4 / M5 可并行）→ M6

---

## M0 基础设施（10 项 / 5d）

| ID | 任务 | 目标文件 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| M0-01 | 激活预留位 | `MesServiceProvider` | 去掉 `enabled` 短路；注册路由/视图/迁移 | `route:list` 见 `mes.*` | 0.5d |
| M0-02 | 填充产品记录 | `config/products.php` 的 `mes` | `description`/`features`/`demo_account`/`theme`（深蓝+橙） | 记录完整 | 0.5d |
| M0-03 | 迁移文件 1：系统 | `Migrations/*_create_system_tables.php` | `users`（含 `employee_no`/`team`/`status`）、`notifications`、spatie 5 张 | 8 表 | 0.5d |
| M0-04 | 迁移文件 2：主数据 | `*_create_master_tables.php` | `materials`、`boms`、`bom_lines`、`routings`、`routing_operations`、`work_centers`、`shifts` | 7 表 | 1d |
| M0-05 | 迁移文件 3：工单 | `*_create_work_order_tables.php` | `work_orders`、`work_order_operations`、`production_reports` | 3 表 | 0.5d |
| M0-06 | 迁移文件 4：物料流转 | `*_create_issue_tables.php` | `material_issues`、`material_issue_lines` | 2 表 | 0.5d |
| M0-07 | 迁移文件 5：质量 | `*_create_quality_tables.php` | `inspection_templates`、`inspections`、`inspection_results`、`defects` | 4 表 | 0.5d |
| M0-08 | 迁移文件 6：设备与排产 | `*_create_equipment_aps_tables.php` | `equipments`、`equipment_checks`、`equipment_repairs`、`aps_schedules` | 4 表 | 0.5d |
| M0-09 | 21 个模型 | `Models/*` | 全部 `extends BaseModel`；`$fillable`、关联、枚举 | 无裸 `extends Model` | 1d |
| M0-10 | 建库建表 | `php artisan migrate --database=mes` | 6 个迁移全绿 | 21 业务表齐 | 0.5d |

---

## M1 基础数据（12 项 / 8d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| M1-01 | 物料控制器+页面 | `MasterData/MaterialController` + `views/master/materials` | 列表（搜索/分页/分类筛选）、新增/编辑弹窗、库存显示 | CRUD 可跑 | 1d |
| M1-02 | BOM 控制器+页面 | `MasterData/BomController` + `views/master/boms` | 头+行编辑（子件选择、用量、损耗率、行序） | 可保存多行 | 1.5d |
| M1-03 | 工艺路线控制器+页面 | `MasterData/RoutingController` + `views/master/routings` | 工序序列（工序号、工序名、工作中心、标准工时、是否需检验） | 可增删工序 | 1.5d |
| M1-04 | 工作中心控制器+页面 | `MasterData/WorkCenterController` + `views/master/work-centers` | 列表+表单（车间、日产能） | CRUD | 0.5d |
| M1-05 | 班次控制器+页面 | `MasterData/ShiftController` + `views/master/shifts` | 白班/夜班、跨天标记 | CRUD | 0.5d |
| M1-06 | FormRequest 4 个 | `Http/Requests/Master/*` | 物料/BOM/工艺/工作中心验证 | 校验生效 | 0.5d |
| M1-07 | 基础数据路由 | `Routes/web.php` | `mes.master.*` 5 组 | 路由可访 | 0.5d |
| M1-08 | 布局与主题 | `views/layouts/app.blade.php` + `assets/mes/mes.css` | 侧边菜单（基础数据/工单/执行/质量/设备/报表/系统）、深蓝+橙 | 菜单可按角色隐藏 | 1d |
| M1-09 | 种子：物料 40 | `Seeders/MaterialSeeder` | 原料 15 / 半成品 10 / 成品 15，`MJ-` 编码 | 列表有 40 条 | 0.5d |
| M1-10 | 种子：BOM 8 + 工艺 8 + 工作中心 4 + 班次 2 | `Seeders/MasterSeeder` | 每个成品 3–6 子件、3–5 工序 | 数据齐 | 1d |
| M1-11 | 导入中心页 | `ImportController@index` + `views/import/index` | 四张卡片：导入物料/BOM/工单、导出报工；带模板下载链接 | 页面可用 | 0.5d |
| M1-12 | 三个导入模板 | `resources/templates/*.xlsx` + `@template` | 物料/BOM/工单固定模板，含示例行与字段说明 | 可下载 | 0.5d |
| M1-13 | 上传与预览校验 | `@store/@confirm` + `ImportService` | 解析 Excel → 逐行校验 → 预览前 10 行 + 标红错误行 → 确认入库 | 故意导错能标红 | 1d |
| M1-14 | 导出 | `ExportController` | 报工汇总、不良明细导出 Excel | 文件可打开 | 0.5d |
| M1-15 | 阶段回归 | — | 五个基础数据页面 CRUD + 一轮"导出→导入"闭环 | 无报错 | 0.5d |
| M1-16 | 门禁 | G-01~G-17 + MC-01/MC-02 | 全绿 | — | 0.5d |

---

## M2 工单与排产（12 项 / 9d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| M2-01 | 工单服务 | `Services/WorkOrderService` | 创建：选成品+BOM+数量 → 按 BOM 展开物料需求、按工艺展开工序 | 单测通过 | 1.5d |
| M2-02 | 工单列表页 | `WorkOrderController@index` + `views/work-orders/index` | 工单号/成品/数量/状态/进度条/计划日期，筛选 | 20 条可显示 | 1d |
| M2-03 | 工单详情页 | `@show` + `views/work-orders/show` | 工序进度、报工记录、领料记录、检验记录四个 Tab | 四块数据齐 | 1.5d |
| M2-04 | 工单创建页 | `@create/@store` + `views/work-orders/create` | 选成品后自动带出 BOM 与工艺预览 | 可创建 | 1d |
| M2-05 | 工单下达 | `@release` | `draft`→`released`，生成工序任务 | 状态变化正确 | 0.5d |
| M2-06 | 派工 | `DispatchService` + `@dispatch` | 工序派给班组/人 + 计划起止时间 | 工序状态→`dispatched` | 1d |
| M2-07 | 排产服务 | `Services/ApsService` | 按工作中心日产能贪心排产，写 `aps_schedules` | 单测通过 | 1.5d |
| M2-08 | APS 页面 | `ApsController@index` + `views/aps/index` | 甘特图：工作中心 × 5 天，工单色块、可拖拽调序 | 图正常渲染 | 1.5d |
| M2-09 | 排产触发 | `@schedule` | 一键排产，结果刷新甘特 | 可用 | 0.5d |
| M2-10 | 种子：20 工单 | `Seeders/WorkOrderSeeder` | 状态覆盖 3/5/8/4；含"卡质检""缺料停线""良率偏低"各一张 | 列表可讲故事 | 1d |
| M2-11 | 种子：排产结果 | `Seeders/ApsSeeder` | 未来 5 天 20 条 | 甘特有内容 | 0.5d |
| M2-12 | 门禁 | MC-07（状态机可走通） | 全绿 | — | 0.5d |

---

## M3 生产执行（10 项 / 7d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| M3-01 | 报工服务 | `Services/ReportService` | 累计良品/不良、推进工序状态、工时统计 | 单测通过 | 1d |
| M3-02 | 报工页（工单内） | `views/work-orders/report` | 良品数/不良数/工时/班次，**手机优先布局** | 375px 可用 | 1d |
| M3-03 | 报工流水页 | `WorkOrderReportController@index` + `views/reports/index` | 按工序/人/日期筛选 | 120 条可查 | 0.5d |
| M3-04 | 领料服务 | `Services/IssueService` | 按 BOM 算需求、生成行、逐行发料扣减库存 | 单测通过 | 1d |
| M3-05 | 领料列表页 | `MaterialIssueController@index` + `views/issues/index` | 工单→明细，已发/未发/部分发 | 12 张可查 | 1d |
| M3-06 | 发料操作 | `@store` | 逐行录入实发数量，更新状态 | 库存变化正确 | 0.5d |
| M3-07 | 我的任务（操作工） | `views/work-orders/my` + 菜单入口 | 只显示派给自己的工序，一键跳到报工 | 角色切换可见 | 1d |
| M3-08 | 种子：报工 120 条 | `Seeders/ReportSeeder` | 近 7 天，含不良 | 报表有数据 | 0.5d |
| M3-09 | 种子：领料 12 张 | `Seeders/IssueSeeder` | 部分已发、部分待发 | 可演示发料 | 0.5d |
| M3-10 | 门禁 + 回归 | 报工→进度→看板数字联动 | 全绿 | — | 0.5d |

---

## M4 质量管理（10 项 / 7d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| M4-01 | 检验模板页 | `InspectionTemplateController` + `views/quality/templates` | 按工序定义检验项（名称、标准值、上下限） | CRUD | 1d |
| M4-02 | 检验判定服务 | `Services/InspectionService` | 逐项比对 → 自动总体判定 | 单测通过 | 1d |
| M4-03 | 检验单列表 | `InspectionController@index` + `views/quality/inspections/index` | IQC/IPQC/OQC 分类、结果筛选 | 25 张可查 | 0.5d |
| M4-04 | 检验单执行页 | `@show/@submit` + `views/quality/inspections/show` | 逐项录入实测值，实时显示合格/超差 | 判定正确 | 1.5d |
| M4-05 | 不良记录页 | `DefectController` + `views/quality/defects` | 不良代码、数量、工序、责任、处置方式 | CRUD | 0.5d |
| M4-06 | 返工单页 | `ReworkController` + `views/quality/reworks` | 不良→返工任务，关联原工单 | 可创建 | 0.5d |
| M4-07 | 质检待办入口 | `views/dashboard` 增加质检待办卡片 | 待检 3 张 | 可见 | 0.5d |
| M4-08 | 种子：模板 6 + 检验单 25 + 不良 15 + 返工 4 | `Seeders/QualitySeeder` | 含 3 张不合格、5 种不良代码 | 数据齐 | 1d |
| M4-09 | 回归：检验→不良→返工 | — | 三页联动 | 可跑通 | 0.5d |
| M4-10 | 门禁 | MC-06（表-页面映射） | 全绿 | — | 0.5d |

---

## M5 设备管理（6 项 / 4d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| M5-01 | 设备台账 | `EquipmentController@index/@store/@edit` + `views/equipment/index` | 编号、名称、所属工作中心、状态、保养周期 | CRUD | 1d |
| M5-02 | 点检页 | `@checks/@checkStore` + `views/equipment/checks` | 每日点检项与结果、上次点检日期 | 可录入 | 1d |
| M5-03 | 报修页 | `@repairs/@repairStore` + `views/equipment/repairs` | 报修单、故障描述、处理人、停机时长、状态 | 可流转 | 1d |
| M5-04 | 种子：12 设备 + 60 点检 + 5 报修 | `Seeders/EquipmentSeeder` | 含 2 台维修中、1 张未闭环报修 | 数据齐 | 0.5d |
| M5-05 | 设备状态看板卡 | 看板增加"设备运行 10/12" | 数字准确 | 0.5d |
| M5-06 | 门禁 | — | 全绿 | — | 0.5d |

---

## M6 看板/报表/系统/收尾（8 项 / 5d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| M6-01 | 看板聚合服务 | `Services/KanbanService` | 今日计划/完成/在制/良率/异常/设备 | 数字与业务表一致 | 1d |
| M6-02 | 车间看板页 | `KanbanController` + `views/kanban/index` | 大字号卡片、自动刷新（30s）、适合大屏 | 1920×1080 好看 | 1.5d |
| M6-03 | 三张报表 | `Reports\{Output,Yield,Labor}Controller` + 3 个视图 | 产量趋势、良率趋势+不良 Top5、工时效率 | 图表可渲染 | 1.5d |
| M6-04 | 系统页 | `System\{User,Role,Notification}Controller` + 视图 | 用户/角色/权限分配、通知 | 三页可用 | 1d |
| M6-05 | 五个角色种子 | `Seeders/RolePermissionSeeder` | admin/planner/supervisor/operator/qc + 权限矩阵 | 切角色菜单变化 | 1d |
| M6-06 | 五个演示账号 | `Seeders/UserSeeder` | 密码 123456 | 五账号可登录 | 0.5d |
| M6-07 | §7 演示脚本走查 | 九步全走 | 每步有数字变化 | 1d |
| M6-08 | 门禁 + 打标 | G-01~G-17 + MC-01~MC-08 | 全绿；tag `S5-mes` | 0.5d |

---

## 验收清单

- [ ] 21 张业务表全部被页面读写（无死表，MC-06）
- [ ] 六组状态机全部可走通（MC-07）
- [ ] 报工页 375px 可用（MC-08）
- [ ] 五角色菜单差异正确
- [ ] 看板数字与报工/工单一致
- [ ] 与 ERP 无任何类/表/连接引用（MC-03）
- [ ] 物料编码全 `MJ-`，与 ERP `MAT-` 无交集（MC-02）
- [ ] 九步演示脚本全部走通且数字联动
- [ ] 无定时任务（MC-05）
- [ ] 门禁全绿
