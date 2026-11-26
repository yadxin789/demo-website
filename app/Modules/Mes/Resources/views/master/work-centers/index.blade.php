@extends('mes::layouts.app')

@section('title', '工作中心')
@section('sub', '车间 / 产线 · 日产能以分钟计')

@section('content')
    <div class="mes-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px">
            <div style="color:#6b7a90; font-size:13px">
                产能口径：一条产线一天可用的机器工时（分钟）。排产时按它做贪心分派。
            </div>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#wcModal" onclick="fillWc(null)">+ 新增工作中心</button>
        </div>

        <table class="mes-table">
            <thead>
            <tr>
                <th>编码</th>
                <th>名称</th>
                <th>车间</th>
                <th class="num">日产能（分钟）</th>
                <th class="num">折合工时</th>
                <th class="num">在制工序</th>
                <th>状态</th>
                <th style="width:70px"></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($centers as $c)
                <tr>
                    <td style="font-family:ui-monospace,Consolas,monospace">{{ $c->code }}</td>
                    <td>{{ $c->name }}</td>
                    <td style="color:#6b7a90">{{ $c->workshop ?: '—' }}</td>
                    <td class="num">{{ $c->daily_capacity }}</td>
                    <td class="num">{{ round($c->daily_capacity / 60, 1) }} h</td>
                    <td class="num">{{ $loads[$c->id] ?? 0 }}</td>
                    <td>
                        @php $map = ['active' => ['b-done', '运行'], 'idle' => ['b-draft', '空闲'], 'maintenance' => ['b-bad', '检修']]; @endphp
                        <span class="mes-badge {{ $map[$c->status][0] ?? 'b-draft' }}">{{ $map[$c->status][1] ?? $c->status }}</span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-outline-secondary py-0 px-2"
                                data-bs-toggle="modal" data-bs-target="#wcModal"
                                onclick='fillWc(@json($c))'>编辑</button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="modal fade" id="wcModal" tabindex="-1">
        <div class="modal-dialog">
            <form class="modal-content" method="post" id="wcForm" action="{{ route('mes.master.work-centers.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST" id="wcMethod">
                <div class="modal-header">
                    <h5 class="modal-title" id="wcTitle">新增工作中心</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    @if ($errors->any())
                        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
                    @endif
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">编码</label>
                            <input name="code" class="form-control" value="{{ old('code') }}" placeholder="WC-XXX" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">名称</label>
                            <input name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">车间</label>
                            <input name="workshop" class="form-control" value="{{ old('workshop') }}">
                        </div>
                        <div class="col-6">
                            <label class="form-label">日产能（分钟）</label>
                            <input name="daily_capacity" type="number" class="form-control" value="{{ old('daily_capacity', 480) }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">状态</label>
                            <select name="status" class="form-select">
                                <option value="active">运行</option>
                                <option value="idle">空闲</option>
                                <option value="maintenance">检修</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">取消</button>
                    <button class="btn btn-primary" type="submit">保存</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
<script>
function fillWc(c) {
    const form = document.getElementById('wcForm');
    const method = document.getElementById('wcMethod');
    const title = document.getElementById('wcTitle');

    if (!c) {
        form.action = "{{ route('mes.master.work-centers.store') }}";
        method.value = 'POST';
        title.textContent = '新增工作中心';
        form.reset();
        return;
    }
    form.action = "/mes/master/work-centers/" + c.id;
    method.value = 'PUT';
    title.textContent = '编辑工作中心 · ' + c.code;
    form.querySelector('[name=code]').value = c.code;
    form.querySelector('[name=name]').value = c.name;
    form.querySelector('[name=workshop]').value = c.workshop || '';
    form.querySelector('[name=daily_capacity]').value = c.daily_capacity;
    form.querySelector('[name=status]').value = c.status;
}
</script>
@endpush
