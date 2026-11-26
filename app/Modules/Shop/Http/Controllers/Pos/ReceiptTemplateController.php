<?php

namespace App\Modules\Shop\Http\Controllers\Pos;

use App\Modules\Shop\Http\Controllers\Controller;
use App\Modules\Shop\Models\ReceiptTemplate;
use App\Modules\Shop\Services\Audit\AuditLogger;
use App\Modules\Shop\Services\Pos\ReceiptTemplateService;
use App\Modules\Shop\Support\EscPosBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * 小票模板配置：抬头/地址/电话/页脚与打印项开关，保存后即时生效于
 * ESC/POS 指令与浏览器备选模板；右侧给出 32 列文本预览。
 */
class ReceiptTemplateController extends Controller
{
    public function __construct(
        private readonly ReceiptTemplateService $templates,
        private readonly AuditLogger $audit,
    ) {
    }

    public function index(): View
    {
        $template = $this->templates->current();
        $sample = $this->templates->sample();
        $preview = (new EscPosBuilder('utf-8', 32))->build($sample)->text();

        return view('shop::pos.receipt-template.index', [
            'template' => $template,
            'preview' => $preview,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'store_name' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'footer' => ['nullable', 'string', 'max:160'],
            'show_discount' => ['nullable', 'boolean'],
            'show_cashier' => ['nullable', 'boolean'],
            'show_barcode' => ['nullable', 'boolean'],
        ], [], [
            'store_name' => '抬头',
            'address' => '地址',
            'phone' => '电话',
            'footer' => '页脚',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $payload = [
            'name' => '默认模板',
            'store_name' => $data['store_name'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'footer' => $data['footer'] ?? null,
            'show_discount' => $request->boolean('show_discount'),
            'show_cashier' => $request->boolean('show_cashier'),
            'show_barcode' => $request->boolean('show_barcode'),
            'is_active' => true,
        ];

        $current = ReceiptTemplate::query()->where('is_active', true)->orderByDesc('id')->first()
            ?? new ReceiptTemplate();

        $old = $current->exists ? $current->only(array_keys($payload)) : null;

        $current->fill($payload)->save();

        $this->audit->record([
            'module' => 'pos',
            'action' => $old === null ? \App\Modules\Shop\Models\AuditLog::ACTION_CREATE : \App\Modules\Shop\Models\AuditLog::ACTION_UPDATE,
            'model' => ReceiptTemplate::class,
            'record_id' => $current->id,
            'field' => null,
            'old_value' => $old,
            'new_value' => $current->only(array_keys($payload)),
            'message' => '小票模板配置保存',
        ]);

        return redirect()
            ->route('shop.pos.receipt-template.index')
            ->with('status', '小票模板已保存，ESC/POS 与浏览器小票即时生效。');
    }
}
