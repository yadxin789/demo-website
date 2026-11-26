<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>小型ERP | POS进销存 | 简易MES定制开发</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: "Microsoft YaHei", sans-serif;
        }
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.7;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .card {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.08);
        }
        h1 {
            text-align: center;
            color: #222;
            margin-bottom: 12px;
            font-size: 28px;
        }
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 32px;
        }
        .btn-wrap {
            text-align: center;
            margin: 36px 0;
        }
        .demo-btn {
            display: inline-block;
            padding: 14px 36px;
            background-color: #2563eb;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 500;
        }
        .demo-btn:hover {
            background-color: #1d4ed8;
        }
        .warn {
            background-color: #fffbeb;
            border: 1px solid #fcd34d;
            padding: 16px;
            border-radius: 8px;
            color: #92400e;
            margin: 24px 0;
        }
        h2 {
            font-size: 20px;
            margin: 28px 0 12px;
            border-left: 4px solid #2563eb;
            padding-left: 12px;
        }
        ul {
            padding-left: 22px;
            color: #444;
        }
        .contact {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #eee;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #888;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>零售POS · 进销存ERP · 简易MES 定制开发</h1>
            <div class="subtitle">技术栈：Laravel12 + PHP8.3 + MySQL8.4 | openEuler 私有化部署</div>

            <div class="btn-wrap">
                <a href="{{ route('shop.login') }}" class="demo-btn">进入系统演示</a>
            </div>

            <div class="warn">
                ⚠️ 演示站说明：本版本仅用于功能预览，数据<strong>每日自动重置清空</strong>，不可用于正式经营业务。
            </div>

            <h2>服务范围</h2>
            <ul>
                <li>百货门店POS收银、小票打印、会员、应收对账</li>
                <li>商贸进销存：采购入库、库存盘点、库存预警、台账报表</li>
                <li>小型工厂简易MES：工单、工序报工、产量统计</li>
                <li>权限角色管理、Excel导入导出、远程部署、操作文档交付</li>
            </ul>

            <h2>服务约定</h2>
            <ul>
                <li>独立开发者交付完整源码、一键部署脚本，方便后续维护迭代</li>
                <li>只做软件开发调试，不做硬件采购、供应链配套、线下应酬</li>
                <li>需求书面确认，变更单独评估工时与费用，避免需求无限蔓延</li>
                <li>付款节点：40%首期 → 45%测试验收 → 15%上线交付尾款</li>
            </ul>

            <div class="contact">
                <h2>联系我</h2>
                <p>微信：<strong>yadxin789</strong></p>
                <p>适合小门店、批发商户、小型加工厂，按需定制轻量化管理系统</p>
            </div>
        </div>
        <div class="footer">
            © 2026 ERP数字化定制服务 | 所有项目远程交付
        </div>
    </div>
</body>
</html>
