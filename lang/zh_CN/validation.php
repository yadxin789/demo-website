<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| 中文校验消息（演示站统一口径）
|--------------------------------------------------------------------------
| Laravel 12 自带只有英文包；这里补齐常用键，让五套系统的表单错误都显示中文。
| 只放通用键，业务专属提示由各控制器自己的 messages 提供。
|
*/

return [

    'accepted' => ':attribute 必须接受。',
    'active_url' => ':attribute 不是有效的网址。',
    'after' => ':attribute 必须晚于 :date。',
    'after_or_equal' => ':attribute 必须晚于或等于 :date。',
    'alpha' => ':attribute 只能由字母组成。',
    'alpha_dash' => ':attribute 只能由字母、数字、下划线和短横线组成。',
    'alpha_num' => ':attribute 只能由字母和数字组成。',
    'array' => ':attribute 必须是数组。',
    'before' => ':attribute 必须早于 :date。',
    'before_or_equal' => ':attribute 必须早于或等于 :date。',
    'between' => [
        'numeric' => ':attribute 必须在 :min 到 :max 之间。',
        'file' => ':attribute 必须在 :min 到 :max KB 之间。',
        'string' => ':attribute 必须在 :min 到 :max 个字符之间。',
        'array' => ':attribute 必须有 :min 到 :max 项。',
    ],
    'boolean' => ':attribute 只能是是或否。',
    'confirmed' => ':attribute 与确认值不一致。',
    'date' => ':attribute 不是有效日期。',
    'date_equals' => ':attribute 必须等于 :date。',
    'date_format' => ':attribute 的格式必须为 :format。',
    'different' => ':attribute 与 :other 不能相同。',
    'digits' => ':attribute 必须是 :digits 位数字。',
    'digits_between' => ':attribute 必须是 :min 到 :max 位数字。',
    'email' => ':attribute 不是有效的邮箱地址。',
    'exists' => '所选的 :attribute 不存在。',
    'file' => ':attribute 必须是文件。',
    'filled' => ':attribute 不能为空。',
    'gt' => [
        'numeric' => ':attribute 必须大于 :value。',
        'file' => ':attribute 必须大于 :value KB。',
        'string' => ':attribute 必须多于 :value 个字符。',
        'array' => ':attribute 必须多于 :value 项。',
    ],
    'gte' => [
        'numeric' => ':attribute 必须大于或等于 :value。',
        'file' => ':attribute 必须大于或等于 :value KB。',
        'string' => ':attribute 必须不少于 :value 个字符。',
        'array' => ':attribute 必须不少于 :value 项。',
    ],
    'image' => ':attribute 必须是图片。',
    'in' => '所选的 :attribute 不在允许范围内。',
    'in_array' => ':attribute 不在 :other 的取值范围内。',
    'integer' => ':attribute 必须是整数。',
    'ip' => ':attribute 不是有效的 IP 地址。',
    'ipv4' => ':attribute 不是有效的 IPv4 地址。',
    'ipv6' => ':attribute 不是有效的 IPv6 地址。',
    'json' => ':attribute 不是有效的 JSON 字符串。',
    'lt' => [
        'numeric' => ':attribute 必须小于 :value。',
        'file' => ':attribute 必须小于 :value KB。',
        'string' => ':attribute 必须少于 :value 个字符。',
        'array' => ':attribute 必须少于 :value 项。',
    ],
    'lte' => [
        'numeric' => ':attribute 必须小于或等于 :value。',
        'file' => ':attribute 必须小于或等于 :value KB。',
        'string' => ':attribute 不能多于 :value 个字符。',
        'array' => ':attribute 不能多于 :value 项。',
    ],
    'max' => [
        'numeric' => ':attribute 不能大于 :max。',
        'file' => ':attribute 不能大于 :max KB。',
        'string' => ':attribute 不能多于 :max 个字符。',
        'array' => ':attribute 不能多于 :max 项。',
    ],
    'mimes' => ':attribute 的文件类型必须是 :values。',
    'mimetypes' => ':attribute 的文件类型必须是 :values。',
    'min' => [
        'numeric' => ':attribute 不能小于 :min。',
        'file' => ':attribute 不能小于 :min KB。',
        'string' => ':attribute 至少 :min 个字符。',
        'array' => ':attribute 至少 :min 项。',
    ],
    'not_in' => '所选的 :attribute 不允许。',
    'not_regex' => ':attribute 的格式不符合要求。',
    'numeric' => ':attribute 必须是数字。',
    'present' => ':attribute 必须存在。',
    'regex' => ':attribute 的格式不符合要求。',
    'required' => ':attribute 必须填写。',
    'required_if' => '当 :other 为 :value 时，:attribute 必须填写。',
    'required_unless' => '除非 :other 为 :values，否则 :attribute 必须填写。',
    'required_with' => '当 :values 存在时，:attribute 必须填写。',
    'required_with_all' => '当 :values 都存在时，:attribute 必须填写。',
    'required_without' => '当 :values 不存在时，:attribute 必须填写。',
    'required_without_all' => '当 :values 都不存在时，:attribute 必须填写。',
    'same' => ':attribute 与 :other 必须一致。',
    'size' => [
        'numeric' => ':attribute 必须是 :size。',
        'file' => ':attribute 必须是 :size KB。',
        'string' => ':attribute 必须是 :size 个字符。',
        'array' => ':attribute 必须包含 :size 项。',
    ],
    'string' => ':attribute 必须是字符串。',
    'timezone' => ':attribute 必须是有效的时区。',
    'unique' => ':attribute 已存在，请换一个。',
    'uploaded' => ':attribute 上传失败，请重试。',
    'url' => ':attribute 不是有效的网址。',
    'uuid' => ':attribute 不是有效的 UUID。',

    // 反面说明：允许为空时的提示
    'custom' => [],
    'attributes' => [],
];
