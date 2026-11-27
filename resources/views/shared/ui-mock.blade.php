@php
    $tone = $tone ?? '#4F46E5';
    $gid = 'um' . substr(md5($tone . uniqid('', true)), 0, 6);
@endphp
{{--
    抽象界面示意图（免登录页专用）
    用法：@include('shared::ui-mock', ['tone' => '#4F46E5'])
    说明：不引用任何真实截图，纯 CSS/SVG 绘制，离线可用、体积可忽略。
--}}
<div class="ui-mock" style="--m: {{ $tone }}">
    <div class="ui-mock-bar">
        <span></span><span></span><span></span>
        <em></em>
    </div>
    <div class="ui-mock-body">
        <div class="ui-mock-side">
            <i class="on"></i><i></i><i></i><i></i><i></i><i></i>
        </div>
        <div class="ui-mock-main">
            <div class="ui-mock-head">
                <b></b><b></b>
            </div>
            <div class="ui-mock-kpis">
                <div><small></small><strong>¥68,420</strong><span class="up">+12.5%</span></div>
                <div><small></small><strong>362</strong><span class="up">+8.1%</span></div>
                <div><small></small><strong>18</strong><span class="down">-3.4%</span></div>
            </div>
            <div class="ui-mock-chart">
                <svg viewBox="0 0 320 108" preserveAspectRatio="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="{{ $gid }}" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="{{ $tone }}" stop-opacity=".30"/>
                            <stop offset="100%" stop-color="{{ $tone }}" stop-opacity="0"/>
                        </linearGradient>
                    </defs>
                    <path d="M0,86 L28,74 L56,78 L84,58 L112,64 L140,44 L168,52 L196,34 L224,40 L252,22 L280,30 L320,12 L320,108 L0,108 Z" fill="url(#{{ $gid }})"/>
                    <path d="M0,86 L28,74 L56,78 L84,58 L112,64 L140,44 L168,52 L196,34 L224,40 L252,22 L280,30 L320,12" fill="none" stroke="{{ $tone }}" stroke-width="2.2" stroke-linejoin="round"/>
                    <circle cx="320" cy="12" r="3.4" fill="{{ $tone }}"/>
                </svg>
            </div>
            <div class="ui-mock-foot">
                <div class="ui-mock-donut"></div>
                <div class="ui-mock-bars">
                    <i style="height:42%"></i><i style="height:66%"></i><i style="height:54%"></i>
                    <i style="height:80%"></i><i style="height:62%"></i><i style="height:94%"></i>
                </div>
            </div>
        </div>
    </div>
</div>
