@extends('shop::layouts.app')

@section('title', '本地备份')
@section('heading', '本地备份与还原')

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <div class="card h-100">
                <div class="card-body">
                    <h2 class="h6 mb-3">创建备份</h2>
                    <p class="text-muted small">
                        备份包含数据库完整导出（{{ $database }}）与本地文件（商品图片），打包为单个 zip 落在
                        <code>storage/app/backups</code>，不进 public 目录。最多保留 {{ $maxFiles }} 个包，单包上限 {{ $maxSizeMb }} MB。
                    </p>

                    @if (session('status'))
                        <div class="alert alert-success py-2" role="status">{{ session('status') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger py-2" role="alert">{{ session('error') }}</div>
                    @endif

                    <form method="post" action="{{ route('shop.backups.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="backup-note" class="form-label">备注（可选）</label>
                            <input type="text" id="backup-note" name="note" maxlength="60" value="{{ old('note') }}"
                                   class="form-control @error('note') is-invalid @enderror" placeholder="如：月末结账前">
                            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-database me-1"></i>立即备份
                        </button>
                    </form>

                    <hr>

                    <h2 class="h6 mb-2">还原须知</h2>
                    <ul class="small text-muted mb-0">
                        <li>还原会覆盖当前数据库与本地文件，请先在测试机验证。</li>
                        <li>还原前系统会校验 zip 结构、manifest 与数据库导出的 sha256；校验失败直接拒绝。</li>
                        <li>还原与备份都会写入审计日志，并在备份目录追加 <code>backup.log</code> 留痕。</li>
                        <li>仅系统管理员（<code>system.user.manage</code>）可创建与还原。</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card h-100 p-0">
                <div class="card-body pb-0 d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0">备份包（{{ count($backups) }}）</h2>
                    <span class="text-muted small">按文件名倒序</span>
                </div>
                <div class="table-wrapper">
                    <table class="table table-hover align-middle mb-0">
                        <caption class="visually-hidden">本地备份包列表</caption>
                        <thead>
                            <tr>
                                <th scope="col">备份包</th>
                                <th scope="col">创建时间</th>
                                <th scope="col">大小</th>
                                <th scope="col">库 / 文件</th>
                                <th scope="col">备注</th>
                                <th scope="col" class="text-end">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($backups as $backup)
                                <tr>
                                    <td>
                                        <code class="small">{{ $backup['name'] }}</code>
                                        @if (! $backup['valid'])
                                            <span class="badge text-bg-warning ms-1">包损坏</span>
                                        @endif
                                    </td>
                                    <td class="small">{{ $backup['created_at'] ? \Illuminate\Support\Carbon::parse($backup['created_at'])->format('Y-m-d H:i:s') : '—' }}</td>
                                    <td class="small">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                                    <td class="small">
                                        {{ number_format($backup['database_size'] / 1024, 1) }} KB / {{ $backup['files'] }} 个
                                    </td>
                                    <td class="small">{{ $backup['note'] !== '' ? $backup['note'] : '—' }}</td>
                                    <td class="text-end">
                                        <form method="post" action="{{ route('shop.backups.restore', ['backup' => $backup['name']]) }}"
                                              onsubmit="return confirm('确定用该备份覆盖当前数据库与本地文件？此操作不可撤销。');">
                                            @csrf
                                            <input type="hidden" name="confirm" value="1">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" @disabled(! $backup['valid'])>
                                                <i class="fa-solid fa-rotate-left me-1"></i>还原
                                                <span class="visually-hidden">{{ $backup['name'] }}</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">还没有备份包，点击左侧「立即备份」创建。</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
