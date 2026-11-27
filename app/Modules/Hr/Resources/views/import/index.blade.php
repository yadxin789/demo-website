@extends('hr::layouts.app')

@section('title', 'Excel 导入导出')
@section('sub', '先预览再入库，错误行会在预览表里标红')

@section('content')
    @php($typeText = ['employee' => '花名册', 'attendance' => '打卡记录'])

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="hr-card">
                <h3><i class="fa-solid fa-user-plus" style="color:#5f3dc4"></i> 导入花名册</h3>
                <div style="font-size:13px; color:#6f6f80; margin:6px 0 10px">
                    列：工号 / 姓名 / 性别 / 手机 / 部门编码 / 岗位编码 / 入职日期 / 学历。<br>
                    工号已存在会覆盖更新部门岗位，不会新增重复人。
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <form class="d-flex gap-2" method="post" action="{{ route('hr.import.upload', 'employee') }}" enctype="multipart/form-data" style="margin:0">
                        @csrf
                        <input class="form-control form-control-sm" name="file" type="file" accept=".csv" style="max-width:220px">
                        <button class="btn btn-sm btn-primary" type="submit">上传预览</button>
                    </form>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.import.template', 'employee') }}">下载模板</a>
                </div>
                <div style="font-size:12px; color:#9a9aad; margin-top:8px">现有员工 {{ $stats['employees'] }} 人</div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="hr-card">
                <h3><i class="fa-solid fa-clock" style="color:#0ca678"></i> 导入打卡</h3>
                <div style="font-size:13px; color:#6f6f80; margin:6px 0 10px">
                    列：工号 / 日期 / 上班打卡 / 下班打卡（HH:MM）。<br>
                    入库后按 S01 标准班自动重算迟到早退，同一人同一天只留一条。
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <form class="d-flex gap-2" method="post" action="{{ route('hr.import.upload', 'attendance') }}" enctype="multipart/form-data" style="margin:0">
                        @csrf
                        <input class="form-control form-control-sm" name="file" type="file" accept=".csv" style="max-width:220px">
                        <button class="btn btn-sm btn-primary" type="submit">上传预览</button>
                    </form>
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.import.template', 'attendance') }}">下载模板</a>
                </div>
                <div style="font-size:12px; color:#9a9aad; margin-top:8px">现有打卡 {{ $stats['records'] }} 条</div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="hr-card">
                <h3><i class="fa-solid fa-money-check-dollar" style="color:#d9480f"></i> 导出工资单</h3>
                <div style="font-size:13px; color:#6f6f80; margin:6px 0 10px">
                    按工资批次导出逐人明细（工号、四段工资、加班费、社保、个税、实发）。
                </div>
                <a class="btn btn-sm btn-outline-primary" href="{{ route('hr.payroll.runs.index') }}">去工资批次页导出</a>
                <div style="font-size:12px; color:#9a9aad; margin-top:8px">现有批次 {{ $stats['runs'] }} 个</div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="hr-card">
                <h3><i class="fa-solid fa-file-arrow-down" style="color:#4c6ef5"></i> 导出考勤月报</h3>
                <form class="d-flex gap-2 align-items-center" method="get" action="{{ route('hr.import.export.attendance') }}" style="margin:0">
                    <input class="form-control form-control-sm" name="month" type="month" value="{{ now()->format('Y-m') }}" style="max-width:160px">
                    <button class="btn btn-sm btn-primary" type="submit">下载 CSV</button>
                </form>
                <div style="font-size:12px; color:#9a9aad; margin-top:8px">逐人逐日的上下班时间与状态，可直接给客户看</div>
            </div>
        </div>
    </div>

    @if ($preview)
        <div class="hr-card mt-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h3 style="margin:0">
                    预览：{{ $typeText[$preview['type']] ?? $preview['type'] }}
                    <span style="font-size:12px; color:#6f6f80">
                        共 {{ count($preview['rows']) }} 行，
                        可入库 {{ collect($preview['rows'])->where('ok', true)->count() }} 行，
                        有问题 {{ collect($preview['rows'])->where('ok', false)->count() }} 行
                    </span>
                </h3>
                <div class="d-flex gap-2">
                    <form method="post" action="{{ route('hr.import.confirm') }}" style="margin:0">
                        @csrf
                        <button class="btn btn-sm btn-primary" type="submit">确认入库</button>
                    </form>
                    <form method="post" action="{{ route('hr.import.cancel') }}" style="margin:0">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary" type="submit">放弃</button>
                    </form>
                </div>
            </div>

            <table class="hr-table">
                <thead>
                <tr><th class="num">行号</th><th>内容</th><th>判定</th><th>说明</th></tr>
                </thead>
                <tbody>
                @foreach ($preview['rows'] as $row)
                    <tr style="{{ $row['ok'] ? '' : 'background:#fff4f6' }}">
                        <td class="num">{{ $row['line'] }}</td>
                        <td style="font-size:12px; font-family:ui-monospace,Consolas,monospace">{{ implode(' / ', array_filter($row['cells'], fn ($c) => $c !== '')) }}</td>
                        <td>
                            @if ($row['ok'])
                                <span class="hr-badge b-done">可入库</span>
                            @else
                                <span class="hr-badge b-bad">有错误</span>
                            @endif
                        </td>
                        <td style="font-size:12px; color:{{ $row['ok'] ? '#6f6f80' : '#d6336c' }}">
                            {{ $row['ok'] ? $row['note'] : implode('；', $row['errors']) }}
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
