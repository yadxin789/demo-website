@extends('hr::layouts.app')

@section('title', '站内通知')
@section('sub', '华瑞集团（控股）· 通知只发给本产品账号，不跨产品')

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="hr-card">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h3 style="margin:0">收件箱 {{ $rows->count() }} 条（未读 {{ $unread }}）</h3>
                    @if ($unread > 0)
                        <form method="post" action="{{ route('hr.system.notifications.read') }}" style="margin:0">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary" type="submit">全部标为已读</button>
                        </form>
                    @endif
                </div>

                <table class="hr-table">
                    <thead><tr><th>时间</th><th>标题</th><th>内容</th><th>来自</th><th>状态</th></tr></thead>
                    <tbody>
                    @forelse ($rows as $n)
                        <tr style="{{ $n->read_at === null ? 'background:#f8f5ff' : '' }}">
                            <td style="font-size:12px; white-space:nowrap">{{ \Illuminate\Support\Carbon::parse($n->created_at)->format('Y-m-d H:i') }}</td>
                            <td><b>{{ $n->title }}</b></td>
                            <td style="font-size:12px; color:#6f6f80">{{ $n->body }}</td>
                            <td style="font-size:12px">{{ $n->from }}</td>
                            <td>
                                @if ($n->read_at === null)
                                    <span class="hr-badge b-progress">未读</span>
                                @else
                                    <span class="hr-badge b-draft">已读</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="hr-empty">收件箱是空的，先在右边发一条试试。</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($isAdmin)
        <div class="col-lg-4">
            <div class="hr-card">
                <h3>发通知</h3>
                <form method="post" action="{{ route('hr.system.notifications.send') }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label">标题</label>
                        <input class="form-control" name="title" maxlength="40" required placeholder="例：本月考勤截止提醒">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">内容</label>
                        <textarea class="form-control" name="body" rows="3" maxlength="200" required placeholder="例：请于 28 日前提交本月异常补卡申请。"></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">类型</label>
                        <select class="form-select" name="level">
                            <option value="info">普通</option>
                            <option value="success">好消息</option>
                            <option value="warning">提醒</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">接收人</label>
                        <div style="display:flex; flex-direction:column; gap:4px">
                            @foreach ($accounts as $a)
                                <label style="display:flex; align-items:center; gap:6px; font-size:13px">
                                    <input name="targets[]" type="checkbox" value="{{ $a->id }}">
                                    {{ $a->name }} <span style="color:#9a9aad; font-size:11px">{{ $a->role_code }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">发送</button>
                    <div style="font-size:12px; color:#9a9aad; margin-top:8px">
                        通知写入本产品库的 <b>notifications</b> 表，只有 hr 账号能看到。
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
@endsection

