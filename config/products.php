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
                'email' => 'admin@erp.demo',
                'password' => '123456',
                'note' => '演示环境统一密码 123456',
            ],
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
                'email' => 'admin@shop.demo',
                'password' => '123456',
                'note' => '演示环境统一密码 123456',
            ],
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
        // 预留位：MES（S5 开发，见 MES-产品规划书.md）
        // 现在只注册，不开入口。禁止建任何路由/控制器/视图（HC-29）
        // -------------------------------------------------------------------
        'mes' => [
            'code' => 'mes',
            'label' => '智能制造 MES',
            'short' => 'MES',
            'tagline' => '车间执行 · 工单 · 质量 · 设备 · 排产',
            'description' => '',   // S5 填
            'features' => [],      // S5 填
            'shots' => [],
            'demo_account' => [
                'email' => 'admin@mes.demo',
                'password' => '123456',
                'note' => '演示环境统一密码 123456',
            ],
            'company' => '精工精密制造（苏州）',
            'order' => 4,
            'enabled' => false,
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
            'description' => '',   // S6 填
            'features' => [],      // S6 填
            'shots' => [],
            'demo_account' => [
                'email' => 'admin@hr.demo',
                'password' => '123456',
                'note' => '演示环境统一密码 123456',
            ],
            'company' => '华瑞集团（控股）',
            'order' => 5,
            'enabled' => false,
            'route_prefix' => 'hr',
            'name_prefix' => 'hr.',
            'view_ns' => 'hr',
            'connection' => 'hr',
            'guard' => 'hr',
            'assets' => 'assets/hr',
            'theme' => ['primary' => '#dc3545', 'accent' => '#b02a37'],
            'module_path' => 'app/Modules/Hr',
            'provider' => App\Modules\Hr\Providers\HrServiceProvider::class,
            'database' => 'hr.sqlite',
        ],

    ],
];
