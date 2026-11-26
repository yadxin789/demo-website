<?php

/*
 * 微信支付 / 支付宝接入资料与开通引导。
 *
 * 本配置只描述“接入资料登记”所需字段和官方入口，不包含真实下单、扣款或回调逻辑。
 */

return [

    'providers' => [

        'wechat' => [
            'name' => '微信支付',
            'title' => '微信支付商户号接入',
            'description' => '适用于微信扫码支付、JSAPI 支付与 APP 支付。先在微信支付商户平台开通产品，再登记商户号与 API 证书资料。',
            'icon' => 'fa-brands fa-weixin',
            'tone' => 'success',
            'portal_name' => '微信支付商户平台',
            'portal_url' => 'https://pay.weixin.qq.com/',
            'secondary_name' => '微信开放平台',
            'secondary_url' => 'https://open.weixin.qq.com/',
            'app_label' => 'APPID / 应用 ID',
            'app_hint' => '公众号、小程序或移动应用的 AppID。扫码支付可填写公众号或服务商应用 AppID。',
            'merchant_label' => '商户号 MCHID',
            'merchant_hint' => '微信支付商户平台“账户中心 → 商户信息”中的商户号。',
            'secret_label' => 'API v3 密钥',
            'secret_hint' => '商户平台“账户中心 → API 安全”中设置的 32 位 API v3 密钥，用于解密回调与敏感信息。',
            'private_key_label' => '商户 API 私钥',
            'private_key_hint' => '下载的 apiclient_key.pem 文件内容，以 BEGIN PRIVATE KEY 开头。',
            'public_key_label' => '微信支付公钥 / 平台证书公钥',
            'public_key_hint' => '用于验签的微信支付公钥或平台证书公钥，可粘贴 PEM 或纯 Base64。',
            'cert_serial_label' => '商户 API 证书序列号',
            'cert_serial_hint' => '商户平台“API 安全 → 商户 API 证书”中的证书序列号，通常为 40 位十六进制。',
            'gateway_url' => 'https://api.mch.weixin.qq.com',
            'gateway_hosts' => ['api.mch.weixin.qq.com'],
            'required' => ['app_id', 'merchant_id', 'api_secret', 'cert_serial', 'private_key', 'public_key'],
            'steps' => [
                '登录微信支付商户平台，完成商户主体认证并开通“Native 支付”或所需支付产品。',
                '在“产品中心 → 开发配置”绑定 APPID，并确认商户号已与对应应用关联。',
                '在“账户中心 → API 安全”设置 API v3 密钥，并申请商户 API 证书。',
                '下载证书后，将商户号、证书序列号、API v3 密钥与 apiclient_key.pem 私钥填入右侧表单。',
                '粘贴微信支付公钥或平台证书公钥，保存后点击“保存并检测连接”。',
                '完成真实下单、签名、回调验签和退款联调后，再在收银台启用微信支付。',
            ],
            'notices' => [
                'API v3 密钥、私钥和公钥只保存在本系统数据库中，保存时加密；页面不会回显完整内容。',
                '检测只验证资料完整度、密钥格式和网关连通性，不会发起真实扣款。',
                '微信支付 V3 的交易签名、回调验签与退款仍需要支付网关适配。',
            ],
        ],

        'alipay' => [
            'name' => '支付宝',
            'title' => '支付宝开放平台应用接入',
            'description' => '适用于支付宝当面付、手机网站支付与 APP 支付。先在开放平台创建应用并签约产品，再登记应用密钥。',
            'icon' => 'fa-brands fa-alipay',
            'tone' => 'primary',
            'portal_name' => '支付宝开放平台',
            'portal_url' => 'https://open.alipay.com/',
            'secondary_name' => '支付宝商家中心',
            'secondary_url' => 'https://b.alipay.com/',
            'app_label' => '应用 APPID',
            'app_hint' => '开放平台“控制台 → 我的应用”中创建的应用 APPID。',
            'merchant_label' => '商户 UID / PID（可选）',
            'merchant_hint' => '商家中心账户的 UID 或 PID；仅用于对账时可选登记。',
            'secret_label' => '接口内容加密密钥 AES（可选）',
            'secret_hint' => '仅在开放平台开启“接口内容加密”时需要，填写对应 AES 密钥。',
            'private_key_label' => '应用私钥',
            'private_key_hint' => '开放平台密钥工具生成的应用私钥，可粘贴纯 Base64 或 PEM。',
            'public_key_label' => '支付宝公钥',
            'public_key_hint' => '开放平台“开发设置 → 接口加签方式”中获取的支付宝公钥，可粘贴纯 Base64 或 PEM。',
            'cert_serial_label' => '应用证书序列号（证书模式可选）',
            'cert_serial_hint' => '使用证书模式时填写应用公钥证书序列号；普通公钥模式可留空。',
            'gateway_url' => 'https://openapi.alipay.com/gateway.do',
            'gateway_hosts' => ['openapi.alipay.com', 'openapi.alipaydev.com'],
            'required' => ['app_id', 'private_key', 'public_key'],
            'steps' => [
                '登录支付宝开放平台，创建网页/移动应用并完成应用审核。',
                '在“产品中心”签约“当面付”或“手机网站支付”等所需产品。',
                '在“开发设置 → 接口加签方式”使用密钥工具生成应用私钥和公钥，并上传应用公钥。',
                '在开放平台获取支付宝公钥，与本应用 APPID、应用私钥一起填入右侧表单。',
                '保存后点击“保存并检测连接”，检查必填资料、密钥格式和开放平台网关连通性。',
                '完成真实下单、异步通知验签与退款联调后，再在收银台启用支付宝。',
            ],
            'notices' => [
                '应用私钥只保存在本系统数据库中，保存时加密；页面不会回显完整内容。',
                '普通公钥模式只需 APPID、应用私钥和支付宝公钥；证书模式再补充证书序列号。',
                '检测只做资料与网络检查，不会调用真实下单接口，也不会产生交易。',
            ],
        ],

    ],

];
