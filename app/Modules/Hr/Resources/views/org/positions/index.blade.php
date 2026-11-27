@extends('hr::layouts.app')

@section('title', '岗位')
@section('sub', '华瑞集团（控股）· 岗位编码 / 职级 / 在职人数 / 薪资标准')

@section('content')
    <div class="hr-card mb-3">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">搜索岗位</label>
                <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="编码或名称">
            </div>
            <div class="col-md-4">
                <label class="form-label mb-1" style="font-size:12px; color:#6f6f80">所属部门</label>
                <select class="form-select form-select-sm" name="dept">
                    <option value="">全部部门</option>
                    @foreach ($departments as $d)
                        <option value="{{ $d->id }}" @selected((int) $deptId === $d->id)>{{ $d->code }} {{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-primary" type="submit">筛选</button>
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.org.positions.index') }}">重置</a>
                <button class="btn btn-sm btn-outline-primary ms-auto" type="button" data-bs-toggle="modal"
                        data-bs-target="#posModal" onclick="openPos(null)">新增岗位</button>
            </div>
        </form>
    </div>

    <div class="hr-card">
        <h3>岗位共 {{ $rows->total() }} 个</h3>
        <table class="hr-table">
            <thead>
            <tr>
                <th>编码</th><th>岗位</th><th>所属部门</th><th>职级</th>
                <th class="num">在职</th><th class="num">基本</th><th class="num">岗位</th><th class="num">绩效</th><th class="num">补贴</th>
                <th>状态</th><th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($rows as $p)
                <tr>
                    <td><span class="code" style="font-family:ui-monospace,Consolas,monospace">{{ $p->code }}</span></td>
                    <td>{{ $p->name }}</td>
                    <td>{{ $p->department?->name ?? '—' }}</td>
                    <td>{{ $p->grade ?? '—' }}</td>
                    <td class="num">{{ (int) ($filledByPos[$p->id] ?? 0) }}</td>
                    @php($st = $structures[$p->id] ?? null)
                    <td class="num">{{ $st ? number_format((float) $st->base, 0) : '—' }}</td>
                    <td class="num">{{ $st ? number_format((float) $st->post, 0) : '—' }}</td>
                    <td class="num">{{ $st ? number_format((float) $st->performance, 0) : '—' }}</td>
                    <td class="num">{{ $st ? number_format((float) $st->allowance, 0) : '—' }}</td>
                    <td>
                        <span class="hr-badge {{ $p->status === 'active' ? 'b-done' : 'b-draft' }}">
                            {{ $p->status === 'active' ? '启用' : '停用' }}
                        </span>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="modal"
                                data-bs-target="#posModal"
                                onclick='openPos({{ json_encode(["id" => $p->id, "code" => $p->code, "name" => $p->name, "department_id" => $p->department_id, "grade" => $p->grade, "status" => $p->status], JSON_UNESCAPED_UNICODE) }})'>编辑</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="11" class="hr-empty">没有匹配的岗位。</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $rows->links() }}</div>
    </div>

    <div class="modal fade" id="posModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="posForm" action="{{ route('hr.org.positions.store') }}">
                    @csrf
                    <input type="hidden" name="_method" id="posMethod" value="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="posTitle">新增岗位</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">岗位编码</label>
                                <input class="form-control" name="code" id="posCode" placeholder="P036" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">岗位名称</label>
                                <input class="form-control" name="name" id="posName" placeholder="战略分析师" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">所属部门</label>
                                <select class="form-select" name="department_id" id="posDept">
                                    <option value="">未指定</option>
                                    @foreach ($departments as $d)
                                        <option value="{{ $d->id }}">{{ $d->code }} {{ $d->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">职级</label>
                                <input class="form-control" name="grade" id="posGrade" placeholder="P3 / M1">
                            </div>
                            <div class="col-6">
                                <label class="form-label">状态</label>
                                <select class="form-select" name="status" id="posStatus">
                                    <option value="active">启用</option>
                                    <option value="inactive">停用</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">取消</button>
                        <button class="btn btn-primary" type="submit">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    function openPos(p) {
        const form = document.getElementById('posForm');
        const title = document.getElementById('posTitle');
        const method = document.getElementById('posMethod');

        if (!p) {
            title.textContent = '新增岗位';
            form.action = "{{ route('hr.org.positions.store') }}";
            method.value = 'post';
            document.getElementById('posCode').value = '';
            document.getElementById('posName').value = '';
            document.getElementById('posDept').value = '';
            document.getElementById('posGrade').value = '';
            document.getElementById('posStatus').value = 'active';
            return;
        }

        title.textContent = '编辑岗位 · ' + p.code;
        form.action = "{{ url('hr/org/positions') }}/" + p.id;
        method.value = 'put';
        document.getElementById('posCode').value = p.code;
        document.getElementById('posName').value = p.name;
        document.getElementById('posDept').value = p.department_id ?? '';
        document.getElementById('posGrade').value = p.grade ?? '';
        document.getElementById('posStatus').value = p.status;
    }
</script>
@endpush
