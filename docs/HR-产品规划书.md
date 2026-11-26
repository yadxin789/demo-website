# HR 产品规划书（人事管理系统）

> HR 作为**独立第六产品**（第五张卡）的完整设计。代码根 `E:\demo-website\app\Modules\Hr`，数据库 `database/hr.sqlite`。
> 前置：主规划书 v3 的 HC-01~HC-34。配套：`HR-任务清单.md`（H0~H5 共 56 项 / 33d）。
> **注意**：ERP 里保留了自己的 `hr_*` 表与 `hr.*`/`hra.*` 路由（决策：不剥离）。HR 产品是**另一套独立系统**，不是升级版。

---

## 第 0 章｜定位与边界

### 0.1 一句话定位

**给人力的系统**：从招人进来，到发工资走人，中间所有人和钱的事都在这里。

### 0.2 与 ERP 内置人事的边界（必须一句话讲清）

| 层 | ERP（`/erp` 的 `hr.*`/`hra.*`） | HR（`/hr`） |
|---|---|---|
| 管什么 | 够用的员工表 + 简单请假 + 工资录入 | 全生命周期：招聘→入职→考勤→请假→薪资→绩效→离职 |
| 谁用 | 会计/行政兼职 | HRBP、人事专员、部门主管、员工自助 |
| 典型界面 | 员工列表、部门列表 | 招聘漏斗、考勤异常处理、工资条、绩效评分 |
| 深度 | 一张 `hr_employees` 表打天下 | 合同、异动、假期余额、薪资结构、绩效指标 |
| 演示话术 | "ERP 顺带管管人" | "HR 专门管人" |

**硬边界（HC-31 / MC 类比）**：演示版 HR 与 ERP **零数据交互**，员工数据各自一份、人名完全不同。

### 0.3 MVP 边界

| 做 | 不做 |
|---|---|
| 组织架构（多级部门）、岗位、编制 | 多法人主体、集团并表 |
| 员工档案、合同、异动（入职/转正/调岗/离职） | 电子签、身份证验真 |
| 班次、打卡记录、考勤异常、加班 | 考勤机硬件对接（用导入/模拟数据） |
| 请假单、假期余额（年假/调休） | 复杂排班规则、跨厂借调 |
| 薪资结构、工资单、工资条明细 | 个税申报、社保公积金真实计算（用简化公式） |
| 招聘需求、候选人、面试、offer | 招聘网站 API 对接 |
| 绩效周期、指标、评分、结果 | 360 评估、强制分布算法 |
| 员工自助（查工资条/请假） | 移动端 App（用响应式网页） |

> 原则同 MES：**每个页面有真实数据，每张表至少被一个页面用到**。

### 0.4 可对接性设计：Excel 导入 / 导出

**为什么做**：HR 的目标客户是"有 ERP 但人事弱"的企业，员工名单在旧系统里、打卡数据在考勤机里。不能导入，客户就得手工录 80 个员工——这一步会直接劝退。

| 方向 | 内容 | 演示说法 |
|---|---|---|
| 导入 | **员工花名册**（工号/姓名/部门/岗位/入职日期/手机/状态） | "您旧系统里的员工表，整理成这个模板就能导进来" |
| 导入 | **考勤打卡记录**（工号/日期/上班时间/下班时间） | "考勤机导出的记录，直接导进来算异常和加班" |
| 导出 | **工资单明细**（工号/姓名/应发/扣款/实发） | "算完的工资表导出去，给财务或银行代发" |
| 导出 | **考勤月报** | "考勤汇总也能导出" |

**实现口径（控制在 1.5d）**：

| 决策 | 选择 |
|---|---|
| 模板 | 两个固定模板（花名册、打卡记录），不做字段映射 |
| 流程 | 下载模板 → 上传 → 预览校验（标红错误行） → 确认入库 |
| 页面 | `/hr/import`（`hr.import.index`）一个页面四张卡片 |
| 代码 | **独立实现，不 import MES 的类**（HC-01）；实现思路相同，代码各自一份 |
| 校验重点 | 花名册必校验：工号唯一、部门必须存在、身份证/手机格式；打卡必校验：工号必须已存在、时间格式 |

> 页面总数由 29 增至 30（`/hr/import`），并在 H6 域内计 1 页。

---

## 第 1 章｜功能域与页面清单

### 1.1 六个功能域

| 域 | 名称 | 页面数 |
|---|---|---|
| H1 | 组织与编制（部门/岗位/编制） | 4 |
| H2 | 员工与异动（档案/合同/异动） | 5 |
| H3 | 考勤与假期（打卡/异常/请假/余额） | 5 |
| H4 | 薪资（结构/工资单/工资条） | 4 |
| H5 | 招聘与绩效（需求/候选人/面试/绩效） | 6 |
| H6 | 报表与系统（人数/考勤/薪资报表、用户角色） | 5 |

### 1.2 页面清单（共 29 个）

| # | 页面 | 路由 | 说明 |
|---|---|---|---|
| 1 | 人力看板 | `hr.dashboard` | 在职人数/本月入职离职/待办（待审请假、待评绩效）/考勤异常数 |
| 2 | 部门树 | `hr.org.departments.index` | 多级树 + 编制数 vs 在职数 |
| 3 | 部门编辑 | `hr.org.departments.edit` | 增删改、上级部门 |
| 4 | 岗位列表 | `hr.org.positions.index` | 岗位、职级、所属部门 |
| 5 | 编制管理 | `hr.org.headcounts.index` | 部门 × 岗位的编制与缺口 |
| 6 | 员工列表 | `hr.employees.index` | 工号 `HR2026001`、姓名、部门、岗位、状态 |
| 7 | 员工详情 | `hr.employees.show` | 基本信息/合同/异动/考勤/薪资 五个 Tab |
| 8 | 员工新增/编辑 | `hr.employees.create/edit` | 分步表单（基本信息→岗位→合同） |
| 9 | 合同管理 | `hr.employees.contracts` | 到期提醒（30/60/90 天） |
| 10 | 异动记录 | `hr.employees.movements` | 入职/转正/调岗/离职流水 |
| 11 | 班次管理 | `hr.attendance.shifts` | 标准班/早班/夜班、打卡时间窗 |
| 12 | 打卡记录 | `hr.attendance.records` | 按日/人/部门筛选，迟到早退标记 |
| 13 | 考勤异常 | `hr.attendance.exceptions` | 缺卡/迟到/早退/旷工，逐条处理 |
| 14 | 加班登记 | `hr.attendance.overtimes` | 加班申请与审批 |
| 15 | 请假列表 | `hr.leaves.index` | 年假/病假/事假/调休，状态流转 |
| 16 | 请假申请 | `hr.leaves.create` | 员工自助提交 |
| 17 | 假期余额 | `hr.leaves.balances` | 每人年假/调休余额与已用 |
| 18 | 薪资结构 | `hr.payroll.structures` | 岗位薪资构成（基本/岗位/绩效/补贴） |
| 19 | 工资单列表 | `hr.payroll.runs.index` | 按月份生成的一批工资单 |
| 20 | 工资单详情 | `hr.payroll.runs.show` | 逐人明细，可导出 |
| 21 | 我的工资条 | `hr.payroll.my` | 员工自助查看（脱敏） |
| 22 | 招聘需求 | `hr.recruit.requisitions` | 岗位需求、人数、进度 |
| 23 | 候选人 | `hr.recruit.candidates` | 简历要点、阶段（筛选/面试/offer/入职） |
| 24 | 面试记录 | `hr.recruit.interviews` | 面试官、评分、结论 |
| 25 | 绩效周期 | `hr.performance.cycles` | 季度/年度周期与参与人 |
| 26 | 绩效评分 | `hr.performance.reviews` | 指标 + 权重 + 自评/上级评 |
| 27 | 绩效结果 | `hr.performance.results` | 分布、排名、与调薪挂钩 |
| 28 | 三张报表 | `hr.reports.headcount/attendance/payroll` | 人数结构、考勤汇总、人力成本 |
| 29 | 系统 | `hr.system.users/roles/notifications` | 用户/角色/通知 |

---

## 第 2 章｜数据模型（22 张业务表 + 6 张基础表）

### 2.1 表清单

| # | 表名 | 说明 | 关键字段 |
|---|---|---|---|
| 1 | `users` | 账号 | `name`, `email`, `password`, `employee_no`, `role_code` |
| 2-6 | spatie 5 张 | 权限 | 标准结构 |
| 7 | `departments` | 部门（多级） | `code`(`D001`), `name`, `parent_id`, `manager_id`, `level`, `status` |
| 8 | `positions` | 岗位 | `code`(`P001`), `name`, `department_id`, `grade`, `status` |
| 9 | `headcounts` | 编制 | `department_id`, `position_id`, `planned`, `filled` |
| 10 | `employees` | 员工档案 | `employee_no`(`HR2026xxx`), `name`, `gender`, `birthday`, `id_card_masked`, `phone`, `email`, `department_id`, `position_id`, `hire_date`, `regular_date`, `leave_date`, `status`(`probation/active/left`) |
| 11 | `employee_contracts` | 合同 | `employee_id`, `contract_no`, `type`(`fixed/open`), `start_date`, `end_date`, `sign_date`, `status` |
| 12 | `employee_movements` | 异动 | `employee_id`, `type`(`hire/regular/transfer/promote/leave`), `effective_date`, `from_position`, `to_position`, `from_dept`, `to_dept`, `remark` |
| 13 | `shifts` | 班次 | `code`, `name`, `work_start`, `work_end`, `late_tolerance_min`, `cross_day` |
| 14 | `attendance_records` | 打卡 | `employee_id`, `work_date`, `clock_in`, `clock_out`, `shift_id`, `status`(`normal/late/early/absent/missing`) |
| 15 | `attendance_exceptions` | 异常 | `attendance_id`, `type`, `reason`, `handler_id`, `handled_at`, `result` |
| 16 | `overtimes` | 加班 | `employee_id`, `date`, `hours`, `type`(`workday/weekend/holiday`), `status`, `approver_id` |
| 17 | `leaves` | 请假 | `leave_no`(`LV-`), `employee_id`, `type`(`annual/sick/personal/compensatory`), `start_date`, `end_date`, `days`, `reason`, `status`(`pending/approved/rejected`), `approver_id` |
| 18 | `leave_balances` | 假期余额 | `employee_id`, `year`, `type`, `total`, `used`, `remaining` |
| 19 | `salary_structures` | 薪资结构 | `position_id`, `base`, `post`, `performance`, `allowance`, `effective_date` |
| 20 | `payroll_runs` | 工资批次 | `period`(`2026-11`), `payroll_no`(`PAY-202611`), `status`, `total_amount`, `generated_at` |
| 21 | `payroll_items` | 工资明细 | `run_id`, `employee_id`, `base`, `post`, `performance`, `allowance`, `overtime_pay`, `leave_deduct`, `social_insurance`, `tax`, `net_pay` |
| 22 | `requisitions` | 招聘需求 | `req_no`(`REQ-`), `position_id`, `headcount`, `urgency`, `status`, `requester_id` |
| 23 | `candidates` | 候选人 | `name`, `phone`, `resume_summary`, `requisition_id`, `stage`(`screening/interview/offer/onboard/reject`) |
| 24 | `interviews` | 面试 | `candidate_id`, `round`, `interviewer_id`, `score`, `result`, `feedback`, `interviewed_at` |
| 25 | `performance_cycles` | 绩效周期 | `name`, `period`, `start_date`, `end_date`, `status` |
| 26 | `performance_indicators` | 绩效指标 | `cycle_id`, `name`, `weight`, `target` |
| 27 | `performance_reviews` | 绩效评分 | `cycle_id`, `employee_id`, `indicator_id`, `self_score`, `manager_score`, `final_score` |
| 28 | `notifications` | 通知 | 标准结构 |

> 业务表 22 张（7~28 去掉 spatie 与 notifications 的重复计数后）。

### 2.2 数据硬约束

| 编号 | 约束 | 原因 |
|---|---|---|
| **HC-M01** | 表名不加 `hr_` 前缀（`employees` 而非 `hr_employees`） | 独立库；与 ERP 的 `hr_employees` 明确不同 |
| **HC-M02** | 工号 `HR2026xxx`；部门 `D001`；岗位 `P001`；请假 `LV-`；工资单 `PAY-202611`；需求 `REQ-` | 与 ERP 的人事编码规则区分 |
| **HC-M03** | 不得引用 ERP 任何类/表/连接 | 独立产品 |
| **HC-M04** | 员工姓名、部门名不得与 ERP 演示数据重复 | 客户一眼看出同源就穿帮 |

### 2.3 状态机

| 对象 | 流转 |
|---|---|
| 员工 | `probation` → `active` → `left` |
| 请假 | `pending` → `approved` / `rejected` |
| 加班 | `pending` → `approved` / `rejected` |
| 考勤异常 | `open` → `handled`（补卡/不计/扣款） |
| 工资单 | `draft` → `confirmed` → `paid` |
| 候选人 | `screening` → `interview` → `offer` → `onboard` / `reject` |
| 绩效周期 | `draft` → `in_progress` → `closed` |

---

## 第 3 章｜目录结构与命名空间

```
app\Modules\Hr\
├── BaseModel.php                      $connection = 'hr'
├── Http\
│   ├── Controllers\
│   │   ├── Auth\LoginController.php
│   │   ├── DashboardController.php
│   │   ├── Org\{DepartmentController, PositionController, HeadcountController}.php
│   │   ├── EmployeeController.php            （列表/详情/增改/合同/异动）
│   │   ├── AttendanceController.php          （班次/记录/异常/加班）
│   │   ├── LeaveController.php               （请假/余额）
│   │   ├── PayrollController.php             （结构/批次/明细/我的工资条）
│   │   ├── RecruitmentController.php         （需求/候选人/面试）
│   │   ├── PerformanceController.php         （周期/指标/评分/结果）
│   │   ├── Reports\{HeadcountController, AttendanceReportController, PayrollReportController}.php
│   │   └── System\{UserController, RoleController, NotificationController}.php
│   ├── Requests\                      约 12 个
│   └── Middleware\
├── Models\                            约 20 个，全部 extends BaseModel
├── Services\
│   ├── EmployeeService.php            入转调离、工号生成
│   ├── AttendanceService.php          打卡判定（迟到/早退/缺卡）、异常生成
│   ├── LeaveService.php               请假审批、余额扣减
│   ├── PayrollService.php             工资计算（结构→明细→汇总）
│   ├── RecruitmentService.php         招聘漏斗推进
│   ├── PerformanceService.php         绩效评分聚合
│   └── DashboardService.php           看板聚合
├── Providers\HrServiceProvider.php
├── Routes\web.php                     prefix('hr') + name('hr.')
├── Database\
│   ├── Migrations\                    约 8 个迁移（按域合并）
│   └── Seeders\
└── Resources\
    ├── views\                         29 个页面 + layouts
    ├── lang\
    └── assets\hr.css + logo + shots
```

---

## 第 4 章｜路由设计

```
/hr/dashboard                            hr.dashboard

/hr/org/departments                      hr.org.departments.index / store / edit / update / destroy
/hr/org/positions                        hr.org.positions.index / store / edit
/hr/org/headcounts                       hr.org.headcounts.index / update

/hr/employees                            hr.employees.index / create / store
/hr/employees/{employee}                 hr.employees.show
/hr/employees/{employee}/edit             hr.employees.edit / update
/hr/employees/{employee}/contracts       hr.employees.contracts / contractStore
/hr/employees/{employee}/movements       hr.employees.movements / movementStore

/hr/attendance/shifts                    hr.attendance.shifts / shiftStore / shiftEdit
/hr/attendance/records                   hr.attendance.records
/hr/attendance/exceptions                hr.attendance.exceptions / exceptionHandle
/hr/attendance/overtimes                 hr.attendance.overtimes / overtimeStore / overtimeApprove

/hr/leaves                               hr.leaves.index / create / store
/hr/leaves/{leave}/approve                hr.leaves.approve / reject
/hr/leaves/balances                      hr.leaves.balances

/hr/payroll/structures                   hr.payroll.structures / structureStore
/hr/payroll/runs                         hr.payroll.runs.index / generate
/hr/payroll/runs/{run}                   hr.payroll.runs.show
/hr/payroll/my                           hr.payroll.my

/hr/recruit/requisitions                 hr.recruit.requisitions / requisitionStore
/hr/recruit/candidates                   hr.recruit.candidates / candidateStore / candidateAdvance
/hr/recruit/interviews                   hr.recruit.interviews / interviewStore

/hr/performance/cycles                   hr.performance.cycles / cycleStore
/hr/performance/reviews                  hr.performance.reviews / reviewSubmit
/hr/performance/results                  hr.performance.results

/hr/reports/headcount                    hr.reports.headcount
/hr/reports/attendance                   hr.reports.attendance
/hr/reports/payroll                      hr.reports.payroll

/hr/system/users                         hr.system.users.index / store / edit
/hr/system/roles                         hr.system.roles.index / store
/hr/system/notifications                 hr.system.notifications.index
```

约 55 条路由。全部 `hr.` 前缀，无裸名（HC-06）。

---

## 第 5 章｜权限与角色

### 5.1 五个角色

| 角色 | code | 可见 | 演示用途 |
|---|---|---|---|
| 管理员 | `admin` | 全部 | 全功能走查 |
| 人事经理 | `hr_manager` | 组织、员工、薪资、绩效审批、报表 | 演示"月度工资核算" |
| 人事专员 | `hr_specialist` | 员工、考勤、招聘、请假审核 | 演示"处理考勤异常" |
| 部门主管 | `dept_manager` | 本部门员工、请假审批、绩效评分 | 演示"主管打分" |
| 员工 | `employee` | 我的工资条、我的请假、我的绩效 | 演示"员工自助" |

### 5.2 五个演示账号（密码 `123456`）

`admin@hr.demo` / `manager@hr.demo` / `specialist@hr.demo` / `dept@hr.demo` / `staff@hr.demo`

---

## 第 6 章｜演示数据（华瑞集团）

### 6.1 公司人设

| 项 | 值 |
|---|---|
| 公司名 | 华瑞集团有限公司（控股） |
| 行业 | 综合集团（含制造、贸易、服务三个板块） |
| 部门 | 12 个（集团办/人力/财务/采购/生产/销售/研发/品质/仓储/IT/法务/客服） |
| 员工 | 80 人（在职 68 / 试用 8 / 离职 4） |
| 岗位 | 25 个（总监/经理/主管/专员/工程师/操作员 等职级） |
| 编制 | 各部门编制合计 90，缺口 22 |
| 编码 | 部门 `D001`–`D012`；岗位 `P001`–`P025`；工号 `HR2026001`–`HR2026080` |

### 6.2 种子数据规模

| 对象 | 数量 | 说明 |
|---|---|---|
| 部门 | 12 | 三级（集团→中心→部门） |
| 岗位 | 25 | 含职级 |
| 编制 | 12 行 | 计划 vs 在职 |
| 员工 | 80 | 在职 68 / 试用 8 / 离职 4；姓名要真实感 |
| 合同 | 80 | 含 6 份 30 天内到期（演示提醒） |
| 异动 | 120 | 入职/转正/调岗/离职流水 |
| 班次 | 3 | 标准/早班/夜班 |
| 打卡 | 68 人 × 22 天 ≈ 1500 条 | 含迟到 30 / 早退 12 / 缺卡 8 |
| 考勤异常 | 50 | 待处理 15 / 已处理 35 |
| 加班 | 40 | 工作日 30 / 周末 10 |
| 请假 | 35 | 待审 5 / 已批 25 / 驳回 5 |
| 假期余额 | 68 行 | 年假 5–15 天 |
| 薪资结构 | 25 | 按岗位 |
| 工资单 | 2 个批次 | 2026-10（已发）/ 2026-11（草稿） |
| 工资明细 | 68 × 2 = 136 条 | 含加班费/请假扣款/社保/个税 |
| 招聘需求 | 8 | 进行中 5 / 完成 3 |
| 候选人 | 30 | 各阶段分布 |
| 面试 | 20 | 含评分 |
| 绩效周期 | 2 | 2026Q3（已结束）/ 2026Q4（进行中） |
| 绩效指标 | 10 | 按部门通用 + 岗位专属 |
| 绩效评分 | 68 × 5 = 340 条 | 自评+上级评 |

> **演示要点**：一定要有"待办"——待审请假 5 条、待处理考勤异常 15 条、待评绩效。客户登录后第一眼看到"有事要做"，才有真实感。

### 6.3 与 ERP 的隔离

| 检查 | 期望 |
|---|---|
| 公司名 | HR="华瑞集团"，ERP="东莞恒创电子" |
| 人名 | 两套完全不同 |
| 工号 | HR 用 `HR2026xxx`；ERP 的 `hr_employees` 用自己的编码 |
| 部门名 | 不重复 |

---

## 第 7 章｜演示主链路（客户 5 分钟）

| 步 | 操作 | 客户看到的 | 时长 |
|---|---|---|---|
| 1 | 打开 `/hr/dashboard` | 在职 68 / 试用 8 / 本月入职 3 / 离职 1 / 待审请假 5 / 考勤异常 15 | 30s |
| 2 | 部门树 | 三级组织，编制 90 vs 在职 68，缺口红字 | 40s |
| 3 | 员工列表 → 某人详情 | 基本信息 + 合同（30 天到期红标）+ 异动流水 + 考勤 + 工资条 | 60s |
| 4 | 考勤异常处理 | 15 条待处理，选一条"缺卡"→ 补卡 → 状态变已处理 | 60s |
| 5 | 请假审批 | 主管视角，批准 1 条 → 年假余额自动扣 1 天 | 50s |
| 6 | 薪资批次 | 2026-11 草稿 → 点"核算" → 68 人生成明细 → 汇总总额 | 60s |
| 7 | 我的工资条（员工视角） | 基本/岗位/绩效/加班/扣款/社保/个税/实发，金额脱敏 | 40s |
| 8 | 绩效评分（主管视角） | 给下属 5 个指标打分，加权算总分 | 50s |
| 9 | 招聘漏斗 | 8 个需求、30 个候选人分布，拖到下一阶段 | 40s |
| 10 | 人力成本报表 | 部门 × 月份的人力成本柱状图 | 30s |

> 关键：第 4/5/6 步都要"数字变化"（异常数 -1、余额 -1、工资单从空到 68 条）。

---

## 第 8 章｜HR 特有硬约束

| 编号 | 规则 | 检查 |
|---|---|---|
| HC-M01 | 表名不加 `hr_` 前缀 | 表名扫描 |
| HC-M02 | 编码前缀规则（`HR2026`/`D`/`P`/`LV-`/`PAY-`/`REQ-`） | 种子抽查 |
| HC-M03 | 不引用 ERP 任何类/表/连接 | 静态扫描 |
| HC-M04 | 人名/部门名/公司名不与 ERP 重复 | 数据比对 |
| HC-M05 | 不注册定时任务 | `Schedule::` 0 命中 |
| HC-M06 | 每张业务表至少被一个页面读写 | 表-页面映射 |
| HC-M07 | 七组状态机全部可走通 | 逐状态验证 |
| HC-M08 | 工资条金额对员工脱敏（只显示本人） | 权限验证 |

---

## 第 9 章｜验收标准

| 维度 | 标准 |
|---|---|
| 独立登录 | `/hr/login`；HR 账号在 ERP 无效 |
| 独立数据 | `hr.sqlite` 与 `erp.sqlite` 零交集 |
| 主链路 | §7 十步全走通，每步有数字变化 |
| 状态机 | 七组全部可走通 |
| 权限 | 五角色菜单/按钮差异正确；员工看不到他人工资条 |
| 敏感数据 | 身份证脱敏、工资条只本人可见 |
| 视觉 | 独立 layout、主题色（建议紫+灰）、独立 logo |
| 门禁 | G-01~G-17 + HC-M01~HC-M08 全绿 |

---

## 附｜与 ERP 内置人事的差异（答疑用）

| 能力 | ERP 的 `hr.*`/`hra.*` | HR 产品 |
|---|---|---|
| 部门 | 有（`hr_departments`） | 有，且带编制与缺口 |
| 员工 | 有（`hr_employees`） | 有，且带合同、异动、试用期 |
| 考勤 | 有（`hr_attendances`） | 有，且带异常处理、加班 |
| 请假 | 有（`hr_leaves`） | 有，且带余额扣减、多级审批 |
| 薪资 | 有（`hr_salaries`，录入式） | 有，且带薪资结构、自动核算 |
| 招聘 | 有（`hr_recruitments`） | 有，且带候选人漏斗、面试评分 |
| 绩效 | 有（`hr_performances`） | 有，且带指标权重、自评+上级评 |
| 报表 | 无 | **有**（人数结构/考勤汇总/人力成本） |
| 员工自助 | 无 | **有**（我的工资条/我的请假/我的绩效） |

> 一句话话术：**"ERP 的人事是给财务算工资用的，HR 是给人力部门管人用的。"**
