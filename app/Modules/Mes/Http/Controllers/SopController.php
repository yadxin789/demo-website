<?php

declare(strict_types=1);

namespace App\Modules\Mes\Http\Controllers;

use App\Modules\Mes\Models\Routing;
use App\Modules\Mes\Models\SopDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 工艺文件（SOP）管理（MES，M7 增补 2）。
 *
 * 页面按「工艺路线 → 工序」列出覆盖情况，可登记/替换 SOP、上传附件、下载现场用。
 */
class SopController extends Controller
{
    public function index(Request $request): View
    {
        $routingId = (int) $request->query('routing', 0);

        $routings = Routing::query()
            ->with(['product:id,code,name', 'operations' => fn ($q) => $q->orderBy('op_no')])
            ->orderBy('code')
            ->get();

        $current = $routingId > 0
            ? $routings->firstWhere('id', $routingId)
            : $routings->first();

        $sops = $current
            ? SopDocument::query()
                ->with('operation:id,routing_id,op_no,op_name,work_center_id')
                ->whereIn('routing_operation_id', $current->operations->pluck('id'))
                ->get()
                ->keyBy('routing_operation_id')
            : collect();

        $total = $current ? $current->operations->count() : 0;
        $covered = $sops->filter(fn (SopDocument $s) => $s->isEffective())->count();

        return view('mes::sop.index', [
            'routings' => $routings,
            'current' => $current,
            'sops' => $sops,
            'stat' => [
                'operations' => $total,
                'covered' => $covered,
                'missing' => max(0, $total - $covered),
                'coverage' => $total > 0 ? round($covered / $total * 100, 1) : 0,
                'total_docs' => SopDocument::query()->count(),
            ],
        ]);
    }

    /** 新建表单：带 ?op= 时预选那道工序 */
    public function create(Request $request): View
    {
        $routings = Routing::query()
            ->with(['product:id,code,name', 'operations' => fn ($q) => $q->orderBy('op_no')])
            ->orderBy('code')
            ->get();

        $opId = (int) $request->query('op', 0);

        return view('mes::sop.form', [
            'sop' => null,
            'routings' => $routings,
            'opId' => $opId,
        ]);
    }

    public function show(SopDocument $sop): View
    {
        $sop->load('operation.workCenter', 'operation.routing.product');

        return view('mes::sop.form', [
            'sop' => $sop,
            'routings' => Routing::query()
                ->with(['product:id,code,name', 'operations' => fn ($q) => $q->orderBy('op_no')])
                ->orderBy('code')
                ->get(),
            'opId' => $sop->routing_operation_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        $op = $this->operation((int) $data['routing_operation_id']);
        $code = ($data['code'] ?? '') ?: sprintf('SOP-%s-%02d', $this->routingCode((int) $op->routing_id), (int) $op->op_no);

        $payload = [
            'routing_operation_id' => $op->id,
            'title' => $data['title'],
            'version' => ($data['version'] ?? '') ?: 'A/0',
            'content' => $data['content'] ?? null,
            'status' => $data['status'],
            'effective_on' => ($data['effective_on'] ?? null) ?: null,
            'author' => $data['author'] ?? (string) auth('mes')->user()?->name,
        ];

        if ($file = $this->storeFile($request)) {
            $payload['file_path'] = $file['path'];
            $payload['file_name'] = $file['name'];
        }

        SopDocument::query()->updateOrCreate(['code' => $code], $payload);

        return redirect()->route('mes.sop.index', ['routing' => $op->routing_id])
            ->with('status', "已登记 SOP：{$code}");
    }

    public function update(Request $request, SopDocument $sop): RedirectResponse
    {
        $data = $this->validateData($request, false);
        $op = $this->operation((int) $data['routing_operation_id']);

        $payload = [
            'routing_operation_id' => $op->id,
            'title' => $data['title'],
            'version' => ($data['version'] ?? '') ?: $sop->version,
            'content' => $data['content'] ?? null,
            'status' => $data['status'],
            'effective_on' => ($data['effective_on'] ?? null) ?: null,
            'author' => $data['author'] ?? $sop->author,
        ];

        if ($file = $this->storeFile($request)) {
            $payload['file_path'] = $file['path'];
            $payload['file_name'] = $file['name'];
        }

        $sop->update($payload);

        return redirect()->route('mes.sop.index', ['routing' => $op->routing_id])
            ->with('status', "已更新 SOP：{$sop->code}");
    }

    public function destroy(SopDocument $sop): RedirectResponse
    {
        $routingId = $sop->operation?->routing_id;

        if ($sop->hasFile()) {
            Storage::disk('local')->delete($sop->file_path);
        }
        $sop->delete();

        return redirect()->route('mes.sop.index', ['routing' => $routingId])
            ->with('status', "已删除 SOP：{$sop->code}");
    }

    /** 下载附件；没有附件时把正文导出成 txt，现场也能拿 */
    public function download(SopDocument $sop): StreamedResponse
    {
        if ($sop->hasFile() && Storage::disk('local')->exists($sop->file_path)) {
            return Storage::disk('local')->download($sop->file_path, $sop->file_name ?: basename($sop->file_path));
        }

        $body = "SOP 编号：{$sop->code}\n标题：{$sop->title}\n版本：{$sop->version}\n"
            . '生效日期：' . ($sop->effective_on?->toDateString() ?? '—') . "\n\n" . ($sop->content ?? '');

        return response()->streamDownload(function () use ($body) {
            echo $body;
        }, "{$sop->code}.txt", ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function validateData(Request $request, bool $requireCode = true): array
    {
        return $request->validate([
            'routing_operation_id' => ['required', 'integer', 'exists:mes.routing_operations,id'],
            'code' => [$requireCode ? 'nullable' : 'nullable', 'string', 'max:32'],
            'title' => ['required', 'string', 'max:120'],
            'version' => ['nullable', 'string', 'max:20'],
            'content' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,draft,obsolete'],
            'effective_on' => ['nullable', 'date'],
            'author' => ['nullable', 'string', 'max:64'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,txt,doc,docx,xls,xlsx'],
        ], [], [
            'routing_operation_id' => '工序', 'title' => '标题', 'status' => '状态',
            'effective_on' => '生效日期', 'file' => '附件',
        ]);
    }

    /** @return array{path:string,name:string}|null */
    private function storeFile(Request $request): ?array
    {
        if (! $request->hasFile('file')) {
            return null;
        }

        $file = $request->file('file');
        $path = $file->store('sop', 'local');

        return ['path' => $path, 'name' => $file->getClientOriginalName()];
    }

    private function operation(int $id): object
    {
        return \App\Modules\Mes\Models\RoutingOperation::query()->findOrFail($id);
    }

    private function routingCode(int $routingId): string
    {
        return (string) Routing::query()->whereKey($routingId)->value('code');
    }
}
