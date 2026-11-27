<?php

namespace App\Modules\Erp\Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * P0 初始化：角色+权限+部门+仓库+编号规则+管理员+演示用户+演示数据
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            // v1.3：部门 10 个 + 岗位 29 个（含岗位→角色绑定），由 config/navigation.php 驱动
            PositionSeeder::class,
            // 基础数据归口台账（每类基础数据一个归口部门）
            MasterDataDomainSeeder::class,
            WarehouseSeeder::class,
            NumberSequenceSeeder::class,
            TaxMasterSeeder::class,
            PaymentTermSeeder::class,
            AdminSeeder::class,
            DemoUserSeeder::class,
            // v1.3：29 岗位角色 + 4 兼容角色 + 演示账号岗位绑定（必须在账号创建之后）
            NavigationRoleSeeder::class,
            DemoDataSeeder::class,
            // P1-04 端到端验收剧本（客户/供应商/物料/BOM/销售单/采购单/工单，测试与演示共用）
            AcceptanceSeeder::class,
            ApsSeeder::class,
            // P1-02 A 类物料虚拟标准价（依赖 Acceptance/DemoData 的库存）
            DemoStandardCostSeeder::class,
            // P1-01 六条业务审批流（依赖演示账号 id）
            ApprovalFlowSeeder::class,
            // 研发交付物 37 类字典（创建项目页的任务类型与参与部门来源，必须早于项目模板）
            RndTaskTypeSeeder::class,
            // v1.3 S6b：项目模板预置（2 个可点样例）
            ProjectTemplateSeeder::class,
            // P3-08 电商交易闭环演示店铺与订单（不配置外部接口，不发真实请求）
            EcomDemoSeeder::class,
            // 演示站专用：admin/123456，全权限（客户点开就能跑完整流程）
            DemoAdminSeeder::class,
            // 演示数据覆盖：五套系统全部业务列表页都要有内容，且能演示完整单据链路
            DemoCoverageSeeder::class,        // 第一批：销售/采购/生产/库存/质量/审批/通知
            DemoCoverageExtraSeeder::class,   // 第二批：财务/CRM/人事/商品营销/平台事件/协同/研发/计划/AI/多租户/企微/语音/电商
            DemoCoverageFinalSeeder::class,   // 第三批：设计图档/回收站/目录类主数据补充
        ]);
    }
}
