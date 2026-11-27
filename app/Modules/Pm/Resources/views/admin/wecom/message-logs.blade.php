@extends('pm::layouts.app')

@section('title', '企业微信消息日志')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h4 class="mb-0"><i class="fab fa-weixin me-2"></i>企业微信消息日志</h4>
        <div>
            <a href="{{ route('pm.admin.wecom.settings') }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa fa-cog me-1"></i>集成配置
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:70px;">ID</th>
                            <th style="width:160px;">接收人</th>
                            <th style="width:90px;">类型</th>
                            <th>标题 / 内容</th>
                            <th style="width:110px;">返回码</th>
                            <th style="width:170px;">发送时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                        <tr>
                            <td>{{ $log->id }}</td>
                            <td>{{ $log->user->name ?? '（未绑定用户）' }}</td>
                            <td><span class="badge bg-secondary">{{ $log->msg_type }}</span></td>
                            <td>
                                <div class="fw-semibold">{{ $log->title }}</div>
                                <div class="text-muted small text-truncate" style="max-width: 460px;">{{ $log->content }}</div>
                            </td>
                            <td>
                                @if($log->isSuccess())
                                    <span class="badge bg-success">成功</span>
                                @else
                                    <span class="badge bg-danger">{{ $log->response_code }}</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-5">
                                暂无消息日志。企业微信尚未配置，或尚未通过企微发送过通知。
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($logs->hasPages())
        <div class="card-footer bg-white">{{ $logs->links() }}</div>
        @endif
    </div>
</div>
@endsection
