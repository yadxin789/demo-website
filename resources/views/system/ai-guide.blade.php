@extends('system.layout')

@section('title', 'AI 导入引导')
@section('sub', '把线下表格变成可导入的结构化数据')

@section('content')
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">四步走</div>
            <div class="card-body">
                <ol class="small mb-0 ps-3">
                    <li class="mb-2"><strong>整理源表</strong>：一行一个人/部门/岗位，不要合并单元格，表头写清含义。</li>
                    <li class="mb-2"><strong>生成提示词</strong>：复制右侧的提示词模板，把源表内容一起发给 AI 助手。</li>
                    <li class="mb-2"><strong>校验结果</strong>：检查编码唯一、部门能对上、角色标识存在；不确定的字段留空而不是编造。</li>
                    <li><strong>落库导入</strong>：把校验后的 CSV 交给系统管理员，按用户 → 部门 → 岗位 → 角色的顺序导入。</li>
                </ol>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header">本系统可导入的字段</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th style="width:100px;">对象</th><th>字段</th></tr></thead>
                    <tbody>
                        @foreach ($fields as $entity => $cols)
                            <tr>
                                <td>{{ $entity }}</td>
                                <td class="small font-monospace text-muted">{{ $cols ? implode(', ', $cols) : '该产品未启用此对象' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer small text-muted">导入只影响账号与组织，不写入任何业务单据。</div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>提示词模板</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="copyPrompt()">复制</button>
            </div>
            <div class="card-body">
                <textarea id="promptBox" class="form-control font-monospace" rows="16" readonly>{{ $prompt }}</textarea>
                <div class="small text-muted mt-2" id="copyTip"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function copyPrompt() {
        var box = document.getElementById('promptBox');
        box.select();
        box.setSelectionRange(0, 99999);
        try {
            navigator.clipboard.writeText(box.value);
        } catch (e) {
            document.execCommand('copy');
        }
        document.getElementById('copyTip').textContent = '已复制到剪贴板';
    }
</script>
@endsection
