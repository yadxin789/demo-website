<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 五产品注册表（单一事实来源）
|--------------------------------------------------------------------------
| 门户、预览页、守卫、连接、视图命名空间、资产路径，全部从这里读。
| 任何地方不得硬编码产品名、产品主题色、产品连接名。
|
| 新增产品 = 在这里加一条记录 + 建 app/Modules/{X} 目录 + 建 database/{x}.sqlite
| 关闭产品 = 把 enabled 改 false（门户卡片自动隐藏，所有 URL 直接 404）
|
| ⚠ 本文件不得 include 任何业务类，只能是纯数组（门户在无任何产品库的情况下也要能渲染）
|
*/

return [

    // 默认产品：仅作为"未指定连接"时的兜底，业务查询必须显式指定连接（HC-15）
    'default' => 'erp',

    // 上下文控制
    'context' => [
        'session_key' => 'active_product',
        'header' => 'X-Product',
        'guard_session_prefix' => 'login_',
    ],

    // -----------------------------------------------------------------------
    // 五个产品
    // -----------------------------------------------------------------------
    'products' => [

        'erp' => [
            'code' => 'erp',
            'label' => '电子制造 ERP',
            'short' => 'ERP',
            'tagline' => '供应链 · 生产计划 · 财务一体化',
            'description' => '面向电子制造企业的一体化管理系统：从销售订单、采购、仓库、生产计划到财务核算，一条链路打通。适合还没有任何信息化基础、希望一次到位的中小制造企业。',
            'features' => [
                '销售与采购全流程（报价 → 订单 → 发货 → 对账）',
                '仓库与库存（多仓、批次、盘点、调拨）',
                '生产计划与工单（BOM、物料需求、排产）',
                '财务核算（应收应付、费用、成本）',
                '质量管理（来料/过程/出货检验）',
                '组织与人事（部门、员工、工资表）',
                '报表中心（经营看板、库存周转、毛利分析）',
                '权限体系（角色、数据权限、操作日志）',
            ],
            'shots' => [],
            'demo_account' => [
                'email' => 'admin',
                'password' => '123456',
                'note' => '演示环境统一密码 123456',
            ],
            'suited' => '还没有任何信息化系统、希望一次性把销售、采购、仓库、生产、财务都管起来的中小型制造企业。',
            'suited_short' => '零基础一步到位的中小工厂',
            'crosssell' => '已经有别家 ERP 的客户不必换：可以单独上 MES 管车间、单独上 HR 管人和钱。',
            'company' => '东莞恒创电子有限公司',
            'order' => 1,
            'enabled' => true,
            'route_prefix' => 'erp',
            'name_prefix' => 'erp.',
            'view_ns' => 'erp',
            'connection' => 'erp',
            'guard' => 'erp',
            'assets' => 'assets/erp',
            'theme' => ['primary' => '#0d6efd', 'accent' => '#0b5ed7'],
            'module_path' => 'app/Modules/Erp',
            'provider' => App\Modules\Erp\Providers\ErpServiceProvider::class,
            'database' => 'erp.sqlite',
        ],

        'pm' => [
            'code' => 'pm',
            'label' => '研发项目管理',
            'short' => 'PM',
            'tagline' => '项目 · 任务 · 工时 · 研发流程',
            'description' => '面向研发团队与项目型公司的项目管理系统：立项、任务分解、进度跟踪、工时填报、文档与评审一条线。适合软件公司、设计公司、工程承接方。',
            'features' => [
                '项目立项、模板与版本管理',
                '任务分解与多部门协同执行',
                '任务审核与延期申请流程',
                '甘特图与项目进度跟踪',
                '部门、角色与数据权限',
                '项目文件与版本（含回收站）',
                '知识库与项目模板库',
                '待办、站内通知与操作日志',
            ],
            'shots' => [],
            'demo_account' => [
                'email' => 'admin',
                'password' => '123456',
                'note' => '演示环境统一密码 123456',
            ],
            'suited' => '软件公司、设计公司、工程承接方等项目型团队，靠项目和工时算成本的组织。',
            'suited_short' => '项目型团队与研发公司',
            'crosssell' => '只缺项目与工时管理、不缺财务进销存的团队，可以直接用 PM，不必上整套 ERP。',
            'company' => '深圳智软科技有限公司',
            'order' => 2,
            'enabled' => true,
            'route_prefix' => 'pm',
            'name_prefix' => 'pm.',
            'view_ns' => 'pm',
            'connection' => 'pm',
            'guard' => 'pm',
            'assets' => 'assets/pm',
            'theme' => ['primary' => '#198754', 'accent' => '#146c43'],
            'module_path' => 'app/Modules/Pm',
            'provider' => App\Modules\Pm\Providers\PmServiceProvider::class,
            'database' => 'pm.sqlite',
        ],

        'shop' => [
            'code' => 'shop',
            'label' => '百货零售 ERP',
            'short' => 'Shop',
            'tagline' => 'POS 收银 · 门店 · 仓储 · 日结',
            'description' => '面向零售门店的收银与进销存系统：前台 POS 扫码收银、后台商品与库存、门店调拨、每日对账。适合便利店、百货、连锁小超市。',
            'features' => [
                'POS 收银（扫码、挂单、退货、多种支付）',
                '商品与条码管理',
                '门店库存与调拨',
                '采购入库与供应商对账',
                '会员与储值',
                '日结与交接班',
                '销售报表（品类、时段、门店）',
                '权限与操作日志',
            ],
            'shots' => [],
            'demo_account' => [
                'email' => 'admin',
                'password' => '123456',
                'note' => '演示环境统一密码 123456',
            ],
            'suited' => '便利店、百货、连锁小超市等零售门店，需要一个收银台加一套进销存的后台。',
            'suited_short' => '便利店与连锁小超市',
            'crosssell' => '只有门店收银需求、没有生产环节的客户，用 Shop 比用制造 ERP 更轻。',
            'company' => '悦家连锁百货',
            'order' => 3,
            'enabled' => true,
            'route_prefix' => 'shop',
            'name_prefix' => 'shop.',
            'view_ns' => 'shop',
            'connection' => 'shop',
            'guard' => 'shop',
            'assets' => 'assets/shop',
            'theme' => ['primary' => '#fd7e14', 'accent' => '#e8590c'],
            'module_path' => 'app/Modules/Shop',
            'provider' => App\Modules\Shop\Providers\ShopServiceProvider::class,
            'database' => 'shop.sqlite',
        ],

        // -------------------------------------------------------------------
        // MES（S5 开发中，见 MES-产品规划书.md）
        // -------------------------------------------------------------------
        'mes' => [
            'code' => 'mes',
            'label' => '智能制造 MES',
            'short' => 'MES',
            'tagline' => '车间执行 · 工单 · 质量 · 设备 · 排产',
            'description' => '面向车间现场的执行系统：把 ERP 下达的生产计划拆成工单与工序，派工到班组，扫码/手工报工，卡住不良品，盯住设备停机。已有别家 ERP 的工厂可以直接用它管车间，通过 Excel 导入现有物料与工单。',
            'features' => [
                '物料 / BOM / 工艺路线 / 工作中心主数据',
                '工单创建、下达、派工、进度跟踪',
                '排产甘特图（按工作中心日产能）',
                '生产报工（良品 / 不良 / 工时）',
                '物料领用与发料扣减',
                'IQC / IPQC / OQC 检验与不良处置',
                '设备台账、点检、报修',
                '车间看板与产出 / 良率报表',
                'Excel 导入（物料 / BOM / 工单）与导出',
            ],
            'shots' => [],
            'demo_account' => [
                'email' => 'admin',
                'password' => '123456',
                'note' => '演示环境统一密码 123456',
            ],
            'suited' => '已经有别家 ERP、只想把车间执行管起来的工厂；也适合从零开始、先上车间现场的中小工厂。',
            'suited_short' => '已有 ERP 想补车间管理的工厂',
            'crosssell' => '已有别家 ERP 的工厂优先推荐 MES：物料、BOM、工单都能用 Excel 从现有系统导出再导入，不用替换现有 ERP。',
            'company' => '精工精密制造（苏州）',
            'order' => 4,
            'enabled' => true,
            'route_prefix' => 'mes',
            'name_prefix' => 'mes.',
            'view_ns' => 'mes',
            'connection' => 'mes',
            'guard' => 'mes',
            'assets' => 'assets/mes',
            'theme' => ['primary' => '#6f42c1', 'accent' => '#5a32a3'],
            'module_path' => 'app/Modules/Mes',
            'provider' => App\Modules\Mes\Providers\MesServiceProvider::class,
            'database' => 'mes.sqlite',
        ],

        // -------------------------------------------------------------------
        // 预留位：HR（S6 开发，见 HR-产品规划书.md）
        // -------------------------------------------------------------------
        'hr' => [
            'code' => 'hr',
            'label' => '人事管理 HR',
            'short' => 'HR',
            'tagline' => '组织 · 员工 · 考勤 · 薪资 · 招聘绩效',
            'description' => '从招人进来，到发工资走人，中间所有人和钱的事都在这里：多级组织与编制缺口、员工档案与合同到期、打卡与考勤异常处理、请假审批与假期余额、月度工资核算与工资条、招聘漏斗与绩效打分。员工自己也能登录查工资条、提请假。',
            'features' => [
                '多级组织架构、岗位与编制缺口',
                '员工档案、合同到期提醒、入转调离异动',
                '班次、打卡记录、考勤异常处理、加班审批',
                '请假申请与审批、年假/调休余额扣减',
                '薪资结构、月度工资批次核算、我的工资条',
                '招聘需求、候选人漏斗、面试评分',
                '绩效周期、指标权重、自评与上级评',
                '人数结构 / 考勤汇总 / 人力成本三张报表',
                'Excel 导入员工花名册与打卡记录',
            ],
            'shots' => [],
            'demo_account' => [
                'email' => 'admin@hr.demo',
                'password' => '123456',
                'note' => '演示环境统一密码 123456',
            ],
            'suited' => '50–500 人、人事还在用 Excel 手工算考勤和工资的企业，想要组织、考勤、薪资、招聘一条线。',
            'suited_short' => '人事还在用 Excel 的中型企业',
            'crosssell' => '不缺财务进销存、只缺人事管理的客户，单独上 HR 即可，花名册和打卡记录用 Excel 导入。',
            'company' => '华瑞集团（控股）',
            'order' => 5,
            'enabled' => true,
            'route_prefix' => 'hr',
            'name_prefix' => 'hr.',
            'view_ns' => 'hr',
            'connection' => 'hr',
            'guard' => 'hr',
            'assets' => 'assets/hr',
            'theme' => ['primary' => '#5f3dc4', 'accent' => '#9b7ff0'],
            'module_path' => 'app/Modules/Hr',
            'provider' => App\Modules\Hr\Providers\HrServiceProvider::class,
            'database' => 'hr.sqlite',
        ],

    ],
];

