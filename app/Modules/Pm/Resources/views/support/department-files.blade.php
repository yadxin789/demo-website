@extends('pm::layouts.app')

@section('title', '部门资料文件')

@section('content')
<h1 class="mb-4">部门资料文件</h1>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">本部门资料</h4>
        <div>
            <a href="{{ route('pm.support.index') }}" class="btn btn-sm btn-outline-secondary">返回支援中心</a>
            <a href="{{ route('pm.file.upload-department') }}" class="btn btn-sm btn-primary">上传文件</a>
        </div>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            列表按"全员可见 / 部门可见 / 仅自己可见"的范围过滤，只显示你当前有权查看的资料。
        </p>
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>文件类型</th>
                    <th>文件名</th>
                    <th>版本号</th>
                    <th>权限范围</th>
                    <th>上传人</th>
                    <th>上传时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse($files as $file)
                <tr>
                    <td>{{ $file->category_name }}</td>
                    <td>{{ $file->original_name }}</td>
                    <td>v{{ $file->version }}</td>
                    <td>{{ $file->visibility_name }}</td>
                    <td>{{ $file->uploader->name ?? '—' }}</td>
                    <td>{{ $file->created_at->format('Y-m-d') }}</td>
                    <td>
                        <a href="{{ route('pm.file.download-department', $file) }}" class="btn btn-sm btn-primary">下载</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">本部门暂无可见资料文件</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-3">{{ $files->links() }}</div>
    </div>
</div>
@endsection
