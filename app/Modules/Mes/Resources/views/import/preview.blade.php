@extends('mes::layouts.app')

@section('title', '导入预览 · ' . $def['label'])
@section('sub', ($parsed['file'] ?? '') . ' · ' . ($parsed['at'] ?? '') . ' · 还没有写入任何数据')

@section('content')
    <div class="mes-card" style="margin-bottom:14px">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap">
            <div style="display:flex; gap:28px">
                <div>
                    <div style="font-size:12px; color:#8a97a8">总行数</div>
                    <div style="font-size:22px; font-weight:700">{{ $parsed['total'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#8a97a8">可以入库</div>
                    <div style="font-size:22px; font-weight:700; color:#2f9e44">{{ $parsed['ok'] }}</div>
                </div>
                <div>
                    <div style="font-size:12px; color:#8a97a8">有问题</div>
                    <div style="font-size:22px; font-weight:700; {{ $parsed['bad'] > 0 ? 'color:#d6336c' : 'color:#a8b3c1' }}">{{ $parsed['bad'] }}</div>
                </div>
            </div>
            <div style="display:flex; gap:8px">
                <form method="post" action="{{ route('mes.import.discard', $type) }}">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-secondary">放弃这次导入</button>
                </form>
                @if ($parsed['ok'] > 0)
                    <form method="post" action="{{ route('mes.import.commit', $type) }}">
                        @csrf
                        <button class="btn btn-primary">确认入库 {{ $parsed['ok'] }} 条</button>
                    </form>
                @else
                    <button class="btn btn-primary" disabled>没有可入库的行</button>
                @endif
            </div>
        </div>
        @if ($parsed['bad'] > 0)
            <div style="margin-top:12px; padding:10px 12px; background:#fff5f7; border:1px solid #ffd0da; border-radius:6px; font-size:13px; color:#a61e4d">
                有 {{ $parsed['bad'] }} 行没通过校验：这些行不会入库，其它行照常导入。
                想全部一起导，先在 Excel 里改好这几行再重新上传。
            </div>
        @endif
    </div>

    <div class="mes-card">
        <div class="table-responsive">
            <table class="mes-table" style="font-size:12px">
                <thead>
                <tr>
                    <th class="num" style="width:56px">行号</th>
                    @foreach ($def['columns'] as $col)
                        <th>{{ $col }}</th>
                    @endforeach
                    <th style="width:220px">校验</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($parsed['rows'] as $row)
                    <tr style="{{ $row['errors'] ? 'background:#fff5f7' : '' }}">
                        <td class="num">{{ $row['line'] }}</td>
                        @foreach ($def['columns'] as $col)
                            <td>{{ $row['values'][$col] ?? '' }}</td>
                        @endforeach
                        <td>
                            @if ($row['errors'])
                                @foreach ($row['errors'] as $err)
                                    <div style="color:#c92a2a"><i class="fa-solid fa-circle-exclamation"></i> {{ $err }}</div>
                                @endforeach
                            @else
                                <span class="mes-badge b-done">可入库</span>
                                @foreach ($row['notes'] ?? [] as $note)
                                    <div style="color:#8a6d00; font-size:11px; margin-top:2px">{{ $note }}</div>
                                @endforeach
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div style="margin-top:12px; font-size:12px; color:#8a97a8">
        这一页只是预览，数据库里什么都没变。确认入库会写在一个事务里，中途任何一条写失败就整批回滚。
    </div>
@endsection
