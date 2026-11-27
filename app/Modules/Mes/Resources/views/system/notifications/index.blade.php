@extends('mes::layouts.app')

@section('title', '系统 · 站内通知')
@section('sub', '演示环境不启用推送，这里只做留档展示')

@section('content')
    <div class="mes-kpis">
        <div class="mes-kpi">
            <div class="lbl">通知记录</div>
            <div class="num">{{ $rows->count() }}</div>
            <div class="hint">最近 50 条</div>
        </div>
        <div class="mes-kpi {{ $unread > 0 ? 'orange' : 'ok' }}">
            <div class="lbl">未读</div>
            <div class="num">{{ $unread }}</div>
            <div class="hint">条</div>
        </div>
    </div>

    <div class="mes-card">
        <table class="mes-table" style="font-size:13px">
            <thead><tr><th>时间</th><th>接收人</th><th>标题</th><th>内容</th><th>状态</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace; font-size:12px">{{ $row->at }}</td>
                    <td>{{ $row->notifiable }}</td>
                    <td>{{ $row->title }}</td>
                    <td style="color:#6b7a90">{{ $row->body ?: '—' }}</td>
                    <td><span class="mes-badge {{ $row->read ? 'b-draft' : 'b-released' }}">{{ $row->read ? '已读' : '未读' }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5">
                    <div class="mes-empty">
                        没有任何站内通知。<br>
                        演示版删掉了全部定时任务与消息推送（MC-05），质检待办、缺料停线、设备停机这些提醒改由工作台卡片和车间看板的「异常提醒」承载。
                    </div>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
