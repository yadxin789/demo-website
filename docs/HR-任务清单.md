# HR 任务清单（H0~H5 · 59 项 · 36d）

> 配套：`HR-产品规划书.md`。前置：主项目里程碑 A 完成，`Modules/Hr` 预留位存在。
> 工时单位 `d`（半天 0.5d）。每阶段末门禁全绿（G-01~G-17 + HC-M01~HC-M08）。

---

## 总览

| 阶段 | 目标 | 任务数 | 工时 |
|---|---|---|---|
| H0 | 基础设施 | 10 | 4.5d |
| H1 | 组织与员工 | 12 | 8d |
| H2 | 考勤与假期 | 10 | 7d |
| H3 | 薪资 | 8 | 6d |
| H4 | 招聘与绩效 | 10 | 5.5d |
| H5 | 报表/系统/导入导出/收尾 | 9 | 5d |
| **合计** | | **59** | **36d** |

**依赖**：H0 → H1 → H2 → H3 →（H4 可与 H3 并行）→ H5

---

## H0 基础设施（10 项 / 4.5d）

| ID | 任务 | 目标文件 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| H0-01 | 激活预留位 | `HrServiceProvider` | 注册路由/视图/迁移 | `route:list` 见 `hr.*` | 0.5d |
| H0-02 | 填充产品记录 | `config/products.php` 的 `hr` | description/features/demo_account/theme（紫+灰） | 完整 | 0.5d |
| H0-03 | 迁移 1：系统 | `Migrations/*_create_system_tables.php` | `users`（含 `employee_no`/`role_code`）、`notifications`、spatie 5 张 | 8 表 | 0.5d |
| H0-04 | 迁移 2：组织 | `*_create_org_tables.php` | `departments`、`positions`、`headcounts` | 3 表 | 0.5d |
| H0-05 | 迁移 3：员工 | `*_create_employee_tables.php` | `employees`、`employee_contracts`、`employee_movements` | 3 表 | 0.5d |
| H0-06 | 迁移 4：考勤 | `*_create_attendance_tables.php` | `shifts`、`attendance_records`、`attendance_exceptions`、`overtimes` | 4 表 | 0.5d |
| H0-07 | 迁移 5：假期与薪资 | `*_create_leave_payroll_tables.php` | `leaves`、`leave_balances`、`salary_structures`、`payroll_runs`、`payroll_items` | 5 表 | 0.5d |
| H0-08 | 迁移 6：招聘与绩效 | `*_create_recruit_perf_tables.php` | `requisitions`、`candidates`、`interviews`、`performance_cycles`、`performance_indicators`、`performance_reviews` | 6 表 | 0.5d |
| H0-09 | 20 个模型 | `Models/*` | 全 `extends BaseModel`；关联、枚举、`$fillable` | 无裸继承 | 0.5d |
| H0-10 | 建库建表 | `migrate --database=hr` | 6 迁移全绿 | 22 业务表齐 | 0.5d |

---

## H1 组织与员工（12 项 / 8d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| H1-01 | 部门树页 | `Org/DepartmentController` + `views/org/departments/index` | 多级树（<ul> 树形）、编制 vs 在职、负责人 | 三级树可展开 | 1d |
| H1-02 | 部门编辑 | `@store/@edit/@update/@destroy` | 上级部门选择、层级自动计算 | CRUD | 0.5d |
| H1-03 | 岗位页 | `Org/PositionController` + `views/org/positions` | 岗位、职级、所属部门 | CRUD | 0.5d |
| H1-04 | 编制页 | `Org/HeadcountController` + `views/org/headcounts` | 部门×岗位 计划/在职/缺口（红字） | 缺口正确 | 1d |
| H1-05 | 员工列表 | `EmployeeController@index` + `views/employees/index` | 工号/姓名/部门/岗位/状态，搜索分页 | 80 条可查 | 1d |
| H1-06 | 员工详情 | `@show` + `views/employees/show` | 五 Tab：基本信息/合同/异动/考勤/工资 | 五块齐 | 1d |
| H1-07 | 员工增改 | `@create/@store/@edit/@update` | 分步表单：基本信息→岗位→合同 | 可新增 | 1d |
| H1-08 | 工号生成服务 | `Services/EmployeeService` | `HR2026` + 3 位流水，入职自动生成 | 单测 | 0.5d |
| H1-09 | 合同页 | `@contracts/@contractStore` + 视图 | 到期提醒（30/60/90 天分色） | 6 份到期红标 | 1d |
| H1-10 | 异动页 | `@movements/@movementStore` + 视图 | 入职/转正/调岗/离职流水 | 可登记 | 0.5d |
| H1-11 | 种子：组织 + 员工 | `Seeders/OrgSeeder`、`EmployeeSeeder` | 12 部门/25 岗位/12 编制/80 员工/80 合同/120 异动 | 数据齐 | 1.5d |
| H1-12 | 门禁 + 回归 | — | 组织→员工→合同→异动 全跑 | 全绿 | 0.5d |

---

## H2 考勤与假期（10 项 / 7d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| H2-01 | 班次页 | `AttendanceController@shifts` + 视图 | 标准/早班/夜班、打卡时间窗、迟到容忍 | CRUD | 0.5d |
| H2-02 | 打卡判定服务 | `Services/AttendanceService` | 迟到/早退/缺卡判定、自动生成异常 | 单测 | 1d |
| H2-03 | 打卡记录页 | `@records` + 视图 | 按日/人/部门筛选，状态色标 | 1500 条可查 | 1d |
| H2-04 | 考勤异常页 | `@exceptions/@exceptionHandle` + 视图 | 15 待处理、处理方式（补卡/不计/扣款） | 处理一条→待办-1 | 1d |
| H2-05 | 加班页 | `@overtimes/@overtimeStore/@overtimeApprove` | 申请+审批流 | 状态流转 | 0.5d |
| H2-06 | 请假列表 | `LeaveController@index` + 视图 | 类型/状态筛选，待审 5 条 | 可查 | 0.5d |
| H2-07 | 请假申请 | `@create/@store` + 视图 | 员工自助、日期区间自动算天数 | 可提交 | 0.5d |
| H2-08 | 请假审批与余额 | `@approve/@reject` + `LeaveService` | 批准后扣年假余额 | 余额-1 | 1d |
| H2-09 | 假期余额页 | `@balances` + 视图 | 每人年假/调休 总额/已用/剩余 | 68 行 | 0.5d |
| H2-10 | 种子：考勤 + 请假 | `Seeders/AttendanceSeeder`、`LeaveSeeder` | 1500 打卡/50 异常/40 加班/35 请假/68 余额 | 数据齐；门禁 | 1d |

---

## H3 薪资（8 项 / 6d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| H3-01 | 薪资结构页 | `PayrollController@structures/@structureStore` | 按岗位：基本/岗位/绩效/补贴 | CRUD | 1d |
| H3-02 | 工资计算服务 | `Services/PayrollService` | 结构→明细：加班费、请假扣款、社保、个税、实发 | 单测，公式可验算 | 1.5d |
| H3-03 | 工资批次列表 | `@runs.index/@generate` | 按月份生成批次，状态草稿/确认/已发 | 生成 68 条 | 1d |
| H3-04 | 工资批次详情 | `@runs.show` + 视图 | 逐人明细表，合计与人均 | 可导出（CSV 按钮） | 1d |
| H3-05 | 我的工资条 | `@my` + 视图 | 员工自助，金额只显示本人 | 其他员工不可见 | 0.5d |
| H3-06 | 敏感数据处理 | 视图层 | 身份证/银行账号脱敏 | 页面无明文 | 0.5d |
| H3-07 | 种子：结构与两批次 | `Seeders/PayrollSeeder` | 25 结构 / 2 批次 / 136 明细 | 数据齐 | 0.5d |
| H3-08 | 门禁 + 回归 | — | 核算→汇总→工资条 全跑 | 全绿 | 0.5d |

---

## H4 招聘与绩效（10 项 / 5.5d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| H4-01 | 招聘需求页 | `RecruitmentController@requisitions/@requisitionStore` | 岗位/人数/紧急度/进度 | CRUD | 0.5d |
| H4-02 | 候选人页 | `@candidates/@candidateStore/@candidateAdvance` | 阶段漏斗（筛选→面试→offer→入职） | 阶段可推进 | 1d |
| H4-03 | 面试页 | `@interviews/@interviewStore` | 面试官/轮次/评分/结论 | 可录入 | 0.5d |
| H4-04 | 招聘漏斗展示 | 视图 | 各阶段人数条形图 | 30 人分布可见 | 0.5d |
| H4-05 | 绩效周期页 | `PerformanceController@cycles/@cycleStore` | 周期、参与人范围 | CRUD | 0.5d |
| H4-06 | 绩效指标页 | `@indicators/@indicatorStore` | 指标+权重（权重和须=100%） | 校验生效 | 0.5d |
| H4-07 | 绩效评分页 | `@reviews/@reviewSubmit` | 自评/上级评/加权总分 | 自动算总分 | 1d |
| H4-08 | 绩效结果页 | `@results` + 视图 | 分布图、排名、平均分 | 68 人结果 | 0.5d |
| H4-09 | 种子：招聘 + 绩效 | `Seeders/RecruitmentSeeder`、`PerformanceSeeder` | 8 需求/30 候选人/20 面试/2 周期/10 指标/340 评分 | 数据齐 | 0.5d |
| H4-10 | 门禁 | HC-M06/M07 | 全绿 | — | 0.5d |

---

## H5 报表/系统/收尾（6 项 / 2d）

| ID | 任务 | 目标 | 明细 | 验收 | 工时 |
|---|---|---|---|---|---|
| H5-01 | 三张报表 | `Reports/*` + 3 视图 | 人数结构（部门/职级/司龄）、考勤汇总、人力成本 | 图表可渲染 | 1d |
| H5-02 | 系统页 | `System\{User,Role,Notification}Controller` | 用户/角色/权限分配、通知 | 三页可用 | 0.5d |
| H5-03 | 五角色种子 | `Seeders/RolePermissionSeeder` | admin/hr_manager/hr_specialist/dept_manager/employee | 切角色菜单变化 | 0.5d |
| H5-04 | 五演示账号 | `Seeders/UserSeeder` | 密码 123456 | 五账号可登录 | 0.5d |
| H5-05 | §7 演示脚本走查 | 十步全走 | 每步有数字变化 | 1d | 0.5d |
| H5-06 | 导入中心页 | `ImportController@index` + 视图 | 四卡片：导入花名册/打卡、导出工资单/考勤月报 | 页面可用 | 0.5d |
| H5-07 | 两个模板 + 上传校验 | `@template/@store/@confirm` | 花名册（工号唯一/部门存在）、打卡（工号存在/时间格式）；标红错误行 | 导错能提示 | 0.5d |
| H5-08 | 导出 | `ExportController` | 工资单明细、考勤月报 | 文件可打开 | 0.5d |
| H5-09 | 门禁 + 打标 | G-01~G-17 + HC-M01~HC-M08 | 全绿；tag `S6-hr` | 0.5d | — |

---

## 验收清单

- [ ] 22 张业务表全部被页面读写（HC-M06）
- [ ] 七组状态机全部可走通（HC-M07）
- [ ] 员工看不到他人工资条（HC-M08）
- [ ] 五角色菜单差异正确
- [ ] 请假批准后年假余额自动扣减
- [ ] 工资核算 68 人明细与总额一致
- [ ] 与 ERP 无任何类/表引用（HC-M03）
- [ ] 人名/公司名/编码与 ERP 无交集（HC-M04）
- [ ] 十步演示脚本全部走通且数字联动
- [ ] 无定时任务（HC-M05）
- [ ] 门禁全绿
