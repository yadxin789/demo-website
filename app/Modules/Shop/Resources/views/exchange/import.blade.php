@extends('shop::layouts.app')

@section('title', $meta['label'].'导入')
@section('heading', $meta['label'].'CSV 导入')

@section('content')
    <nav aria-label="面包屑" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route($meta['index']) }}">{{ $meta['label'] }}列表</a></li>
            <li class="breadcrumb-item active" aria-current="page">CSV 导入</li>
        </ol>
    </nav>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    @if ($importErrors !== [])
        <div class="alert alert-danger" role="alert">
            <p class="mb-2 fw-semibold">
                导入未执行：共 {{ count($importErrors) }} 行校验未通过，整份文件未写入任何数据。请按下表行号修改后重新上传。
            </p>
            <div class="table-wrapper">
                <table class="table table-sm table-borderless mb-0 bg-body">
                    <caption class="visually-hidden">导入错误明细</caption>
                    <thead>
                        <tr>
                            <th scope="col" style="inline-size:6rem;">行号</th>
                            <th scope="col">错误原因</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($importErrors as $error)
                            <tr>
                                <td>第 {{ $error['line'] }} 行</td>
                                <td>{{ $error['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-lg-4">
            <div class="card h-100 p-3">
                <h2 class="h6">① 下载模板</h2>
                <p class="text-muted small">
                    模板含表头与一行示例数据；中文表头可直接填写，英文字段名也可识别。必需列：
                    @foreach ($meta['columns'] as $key => $label)
                        @if (in_array($key, ['code', 'name'], true))<span class="badge text-bg-secondary">{{ $label }}</span>@endif
                    @endforeach
                </p>
                <a href="{{ route('shop.exchange.template', $type) }}" class="btn btn-outline-primary">
                    <i class="fa-solid fa-download me-1"></i>下载{{ $meta['label'] }}模板
                </a>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card h-100 p-3">
                <h2 class="h6">② 备份 / 导出当前数据</h2>
                <p class="text-muted small">导出为 UTF-8 CSV（带 BOM），Excel 双击打开中文不乱码；导出动作写入审计日志。</p>
                <a href="{{ route('shop.exchange.export', $type) }}" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-file-export me-1"></i>导出{{ $meta['label'] }}
                </a>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card h-100 p-3">
                <h2 class="h6">③ 上传导入</h2>
                <form method="post" action="{{ route('shop.exchange.import.store', $type) }}" enctype="multipart/form-data"
                      aria-label="{{ $meta['label'] }}导入表单">
                    @csrf
                    <label for="file" class="form-label">CSV 文件</label>
                    <input type="file" id="file" name="file" accept=".csv,text/csv" required
                           class="form-control @error('file') is-invalid @enderror"
                           aria-describedby="import-help">
                    @error('file')<div class="invalid-feedback" role="alert">{{ $message }}</div>@enderror
                    <p id="import-help" class="form-text">
                        支持 UTF-8（含 BOM）或 GBK 编码的 .csv；单文件 ≤ 5 MB、≤ 5000 行。
                    </p>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-upload me-1"></i>校验并导入
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card mt-3 p-3">
        <h2 class="h6">导入规则</h2>
        <ul class="mb-0 small text-muted">
            <li>先整份校验、全部通过才落库：任一行有错则一行都不写入，避免半截数据。</li>
            <li>错误按 Excel 行号回显（表头为第 1 行），可直接改完重传。</li>
            <li>{{ $meta['label'] }}编码在系统内唯一，文件中重复也会报错并指出首次出现的行号。</li>
            <li>类别、单位、税率按名称或编码匹配已有主数据，匹配不到会提示先补齐基础资料。</li>
            <li>是否启用列可填 1/0、是/否，留空按启用处理。</li>
        </ul>
    </div>
@endsection
