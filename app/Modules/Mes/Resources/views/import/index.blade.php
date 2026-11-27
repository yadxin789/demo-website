@extends('mes::layouts.app')

@section('title', 'Excel 导入')
@section('sub', '已有别家 ERP？把物料、BOM、工单从 Excel 搬进来')

@section('content')
    <div class="mes-card" style="margin-bottom:16px; background:linear-gradient(135deg,#16233f,#1f3a63); color:#fff; border:none">
        <div style="font-size:18px; font-weight:700; margin-bottom:8px">先别急着录数据，把现有表格丢进来就行</div>
        <div style="font-size:13px; line-height:1.9; opacity:.9">
            很多工厂不是没有数据，是数据散在 Excel 里：物料清单一份、BOM 一份、工单另一个人手里一份。<br>
            这里三步走完 —— <b>下模板 → 填好上传 → 系统先标出有问题的行，你确认后入库</b>。<br>
            重复的编码不会硬塞进去，格式不对的行会当场告诉你第几行、错在哪。
        </div>
    </div>

    @foreach ($types as $type => $def)
        @php
            $res = $results[$type] ?? null;
            $wait = $pending[$type] ?? null;
        @endphp
        <div class="mes-card" style="margin-bottom:14px">
            <div style="display:flex; justify-content:space-between; gap:16px; flex-wrap:wrap">
                <div style="flex:1; min-width:280px">
                    <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px">
                        <span class="mes-badge b-done" style="font-size:12px">{{ $loop->iteration }}</span>
                        <div style="font-size:16px; font-weight:700">{{ $def['label'] }}</div>
                    </div>
                    <div style="font-size:12px; color:#6b7a90; line-height:1.8; margin-bottom:10px">
                        模板列：
                        @foreach ($def['columns'] as $col)
                            <span style="display:inline-block; background:#eef3f9; color:#33475b; border-radius:4px; padding:1px 6px; margin:2px 2px 0 0">{{ $col }}</span>
                        @endforeach
                    </div>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('mes.import.template', $type) }}">
                        <i class="fa-solid fa-download"></i> 下载模板
                    </a>
                </div>

                <div style="flex:1; min-width:280px; border-left:1px dashed #e2e8f0; padding-left:16px">
                    <form method="post" action="{{ route('mes.import.upload', $type) }}" enctype="multipart/form-data">
                        @csrf
                        <div style="font-size:12px; color:#6b7a90; margin-bottom:6px">选择填好的 .xlsx 或 .csv（≤ 5MB）</div>
                        <div style="display:flex; gap:8px; align-items:center">
                            <input type="file" name="file" class="form-control form-control-sm" accept=".xlsx,.csv" required>
                            <button class="btn btn-sm btn-primary" style="white-space:nowrap">上传并校验</button>
                        </div>
                    </form>

                    @if ($wait)
                        <div style="margin-top:10px; padding:8px 10px; background:#fff8e6; border:1px solid #ffe0a3; border-radius:6px; font-size:12px">
                            有一份待确认的导入（{{ $wait['total'] }} 行，其中 {{ $wait['bad'] }} 行有问题）。
                            <a href="{{ route('mes.import.preview', $type) }}" style="color:#b26a00; font-weight:600">去看预览</a>
                        </div>
                    @endif

                    @if ($res)
                        <div style="margin-top:10px; padding:8px 10px; background:#eefaf2; border:1px solid #bfe6cd; border-radius:6px; font-size:12px; color:#1b6b3a">
                            最近一次：{{ $res['file'] ?? '' }}（{{ $res['at'] ?? '' }}）新增 {{ $res['created'] ?? 0 }}，
                            更新 {{ $res['updated'] ?? 0 }}，跳过 {{ $res['skipped'] ?? 0 }}，失败 {{ $res['failed'] ?? 0 }}
                            @if (! empty($res['messages']))
                                <div style="margin-top:6px; color:#8a4b00">
                                    @foreach (array_slice($res['messages'], 0, 3) as $msg)
                                        <div>{{ $msg }}</div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    <div class="mes-card">
        <div style="font-weight:600; margin-bottom:10px">几条经验</div>
        <div style="font-size:13px; color:#4a5568; line-height:2">
            · 编码格式固定：物料 <code>MJ-XX-001</code>、BOM <code>BOM-XX-001</code>、工单 <code>WO-2026-001</code>。格式错了系统会直接指出来。<br>
            · 损耗率填 <b>2</b> 或 <b>0.02</b> 都行，系统都按 2% 处理。<br>
            · BOM 导入会按"BOM 编码 + 组件编码"去重：同一个 BOM 里重复的组件只留一行。<br>
            · 工单导入时，如果这个产品已经有工艺路线，会顺带把工序生成好，进车间就能派活。<br>
            · 导入是<b>先预览后入库</b>，预览阶段什么都不写库，可以放心反复试。
        </div>
    </div>
@endsection
