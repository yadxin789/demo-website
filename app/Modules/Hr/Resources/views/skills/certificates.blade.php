@extends('hr::layouts.app')

@section('title', '证书台账')
@section('sub', '证件资质与到期提醒：60 天内到期的高亮，过期红色')

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-4"><div class="hr-card"><div style="color:#6f6f80;font-size:12px">证书总数</div><div style="font-size:24px;font-weight:700">{{ $stat['total'] }}</div></div></div>
        <div class="col-4"><div class="hr-card"><div style="color:#6f6f80;font-size:12px">60 天内到期</div><div style="font-size:24px;font-weight:700;color:#e8590c">{{ $stat['within60'] }}</div></div></div>
        <div class="col-4"><div class="hr-card"><div style="color:#6f6f80;font-size:12px">已过期</div><div style="font-size:24px;font-weight:700;color:#c92a2a">{{ $stat['expired'] }}</div></div></div>
    </div>

    <div class="hr-card mb-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h3 style="margin:0">证书清单</h3>
            <div class="d-flex gap-2">
                <a class="btn btn-sm {{ $expiring ? 'btn-outline-primary' : 'btn-primary' }}" href="{{ route('hr.skills.certificates') }}">全部</a>
                <a class="btn btn-sm {{ $expiring ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('hr.skills.certificates', ['expiring' => 1]) }}">只看 60 天内到期/已过期</a>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.skills.index') }}">回技能矩阵</a>
            </div>
        </div>
        <table class="hr-table">
            <thead><tr><th>员工</th><th>证书</th><th>编号</th><th>发证机构</th><th>发证日期</th><th>到期日期</th><th>剩余</th><th></th></tr></thead>
            <tbody>
            @forelse ($certs as $c)
                @php($days = $c->days_to_expire)
                <tr>
                    <td>{{ $c->employee?->employee_no }} {{ $c->employee?->name }}<div style="font-size:11px;color:#8a97a8">{{ $c->employee?->department?->name }}</div></td>
                    <td>{{ $c->name }}</td>
                    <td style="font-family:ui-monospace,Consolas,monospace">{{ $c->cert_no ?: '—' }}</td>
                    <td>{{ $c->issuer ?: '—' }}</td>
                    <td>{{ $c->issued_on?->toDateString() ?? '—' }}</td>
                    <td>{{ $c->expire_on?->toDateString() ?? '长期' }}</td>
                    <td>
                        @if ($days === null)
                            <span class="hr-badge b-draft">长期</span>
                        @elseif ($days < 0)
                            <span class="hr-badge b-bad">过期 {{ abs($days) }} 天</span>
                        @elseif ($days <= 60)
                            <span class="hr-badge b-draft">剩 {{ $days }} 天</span>
                        @else
                            <span class="hr-badge b-done">剩 {{ $days }} 天</span>
                        @endif
                    </td>
                    <td>
                        <form method="post" action="{{ route('hr.skills.certificates.destroy', $c) }}" onsubmit="return confirm('删除这条证书记录？')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-link p-0 text-danger">删除</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="color:#8a97a8">没有证书记录。</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="hr-card">
        <h3 style="margin:0 0 10px">登记证书</h3>
        <form method="post" action="{{ route('hr.skills.certificates.store') }}">
            @csrf
            <div class="row g-2">
                <div class="col-4">
                    <select class="form-select form-select-sm" name="employee_id" required>
                        <option value="">员工</option>
                        @foreach ($employees as $e)
                            <option value="{{ $e->id }}">{{ $e->employee_no }} {{ $e->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-4"><input class="form-control form-control-sm" name="name" placeholder="证书名称，如电工证" required></div>
                <div class="col-4"><input class="form-control form-control-sm" name="cert_no" placeholder="证书编号"></div>
                <div class="col-4"><input class="form-control form-control-sm" name="issuer" placeholder="发证机构"></div>
                <div class="col-3"><input class="form-control form-control-sm" type="date" name="issued_on"></div>
                <div class="col-3"><input class="form-control form-control-sm" type="date" name="expire_on"></div>
                <div class="col-2"><button class="btn btn-primary btn-sm w-100">登记</button></div>
            </div>
        </form>
    </div>
@endsection
