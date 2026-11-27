@extends('hr::layouts.app')

@section('title', '部门树')
@section('sub', '多级组织与编制缺口')

@section('content')
    <div class="hr-kpis mb-3">
        <div class="hr-kpi purple">
            <div class="lbl">部门数</div>
            <div class="num">{{ $kpi['departments'] }}</div>
            <div class="hint">集团 / 中心 / 部门三级</div>
        </div>
        <div class="hr-kpi">
            <div class="lbl">编制合计</div>
            <div class="num">{{ $kpi['planned'] }}</div>
            <div class="hint">各部门编制之和</div>
        </div>
        <div class="hr-kpi ok">
            <div class="lbl">在职合计</div>
            <div class="num">{{ $kpi['filled'] }}</div>
            <div class="hint">不含离职</div>
        </div>
        <div class="hr-kpi bad">
            <div class="lbl">编制缺口</div>
            <div class="num">{{ $kpi['gap'] }}</div>
            <div class="hint">编制 − 在职</div>
        </div>
    </div>

    <div class="hr-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h3 class="mb-0">组织架构</h3>
            <div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('hr.org.positions.index') }}">看岗位</a>
                <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#deptModal"
                        onclick="openDept(null)">新增部门</button>
            </div>
        </div>

        <div class="hr-tree">
            @php
                $renderRow = function (array $node) use (&$renderRow) {
                    echo '<div class="hr-tree-row" data-dept="' . $node['id'] . '">';
                    echo '<span class="indent" style="width:' . ($node['depth'] * 22) . 'px"></span>';
                    echo '<span class="code">' . e($node['code']) . '</span>';
                    echo '<span class="name">' . e($node['name']) . '</span>';
                    if ($node['status'] !== 'active') {
                        echo '<span class="hr-badge b-draft">停用</span>';
                    }
                    echo '<span class="meta">';
                    echo '<span>负责人：' . e($node['manager'] ?? '未指定') . '</span>';
                    echo '<span>在职 ' . $node['filled'] . ' / 编制 ' . $node['planned'] . '</span>';
                    echo $node['gap'] > 0
                        ? '<span style="color:#d6336c">缺口 ' . $node['gap'] . '</span>'
                        : '<span style="color:#2f9e44">齐编</span>';
                    echo '</span>';
                    echo '<span class="d-flex gap-2" style="margin-left:14px">';
                    echo '<button class="btn btn-sm btn-link p-0" type="button" data-bs-toggle="modal" data-bs-target="#deptModal" onclick="openDept('
                        . htmlspecialchars(json_encode([
                            'id' => $node['id'],
                            'code' => $node['code'],
                            'name' => $node['name'],
                            'parent_id' => $node['parent_id'],
                            'manager_id' => $node['manager_id'],
                            'status' => $node['status'],
                            'sort' => $node['sort'],
                        ], JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ')">编辑</button>';
                    echo '<form method="post" action="' . route('hr.org.departments.destroy', $node['id']) . '" onsubmit="return confirm(\'确认删除该部门？\')" style="margin:0">'
                        . csrf_field() . method_field('delete')
                        . '<button class="btn btn-sm btn-link p-0 text-danger" type="submit">删除</button></form>';
                    echo '</span>';
                    echo '</div>';

                    foreach ($node['children'] as $child) {
                        $renderRow($child);
                    }
                };
            @endphp

            @forelse ($tree as $node)
                @php($renderRow($node))
            @empty
                <div class="hr-empty">还没有部门数据。</div>
            @endforelse
        </div>
    </div>

    {{-- 新增 / 编辑共用 --}}
    <div class="modal fade" id="deptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" id="deptForm" action="{{ route('hr.org.departments.store') }}">
                    @csrf
                    <input type="hidden" name="_method" id="deptMethod" value="post">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deptTitle">新增部门</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">部门编码</label>
                                <input class="form-control" name="code" id="deptCode" placeholder="D020" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">部门名称</label>
                                <input class="form-control" name="name" id="deptName" placeholder="战略投资部" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">上级部门</label>
                                <select class="form-select" name="parent_id" id="deptParent">
                                    <option value="">（顶级）</option>
                                    @foreach ($parents as $p)
                                        <option value="{{ $p->id }}">{{ str_repeat('　', max(0, $p->level - 1)) }}{{ $p->code }} {{ $p->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">部门负责人</label>
                                <select class="form-select" name="manager_id" id="deptManager">
                                    <option value="">未指定</option>
                                    @foreach ($managers as $m)
                                        <option value="{{ $m->id }}">{{ $m->employee_no }} {{ $m->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">排序</label>
                                <input class="form-control" name="sort" id="deptSort" type="number" min="0" value="0">
                            </div>
                            <div class="col-6">
                                <label class="form-label">状态</label>
                                <select class="form-select" name="status" id="deptStatus">
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
    function openDept(node) {
        const form = document.getElementById('deptForm');
        const title = document.getElementById('deptTitle');
        const method = document.getElementById('deptMethod');

        if (!node) {
            title.textContent = '新增部门';
            form.action = "{{ route('hr.org.departments.store') }}";
            method.value = 'post';
            document.getElementById('deptCode').value = '';
            document.getElementById('deptName').value = '';
            document.getElementById('deptParent').value = '';
            document.getElementById('deptManager').value = '';
            document.getElementById('deptSort').value = 0;
            document.getElementById('deptStatus').value = 'active';
            return;
        }

        title.textContent = '编辑部门 · ' + node.code;
        form.action = "{{ url('hr/org/departments') }}/" + node.id;
        method.value = 'put';
        document.getElementById('deptCode').value = node.code;
        document.getElementById('deptName').value = node.name;
        document.getElementById('deptParent').value = node.parent_id ?? '';
        document.getElementById('deptManager').value = node.manager_id ?? '';
        document.getElementById('deptSort').value = node.sort ?? 0;
        document.getElementById('deptStatus').value = node.status;
    }
</script>
@endpush
