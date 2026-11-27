<?php

declare(strict_types=1);

namespace App\Core\Http\Controllers;

use App\Core\Support\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 门户线索捕获（S7-12）
 *
 * 只写 portal.sqlite 的 leads 表；不读任何产品库（HC-14）。
 * 防刷：路由挂 throttle + 表单里放 honeypot（机器人会填，人看不到）。
 */
class LeadController
{
    public function store(Request $request): RedirectResponse
    {
        // honeypot：正常浏览器里这个字段是隐藏的、永远为空
        if ($request->filled('website')) {
            return redirect()->route('portal.home')->withFragment('contact')
                ->with('lead_status', '已收到，我们会在 1 个工作日内联系您。');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'contact' => ['required', 'string', 'max:60'],
            'company' => ['nullable', 'string', 'max:80'],
            'interest' => ['nullable', 'string', 'in:erp,pm,shop,mes,hr,all'],
            'message' => ['nullable', 'string', 'max:500'],
        ], [], [
            'name' => '称呼',
            'contact' => '联系方式',
            'company' => '公司',
            'interest' => '感兴趣的产品',
            'message' => '想了解的问题',
        ]);

        $interest = $data['interest'] ?? 'all';
        if ($interest !== 'all' && ! Product::isEnabled($interest)) {
            $interest = 'all';
        }

        DB::connection('portal')->table('leads')->insert([
            'name' => $data['name'],
            'contact' => $data['contact'],
            'company' => $data['company'] ?? null,
            'interest' => $interest,
            'message' => $data['message'] ?? null,
            'ip' => (string) $request->ip(),
            'ua' => mb_substr((string) $request->userAgent(), 0, 200),
            'status' => 'new',
            'note' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->route('portal.home')->withFragment('contact')
            ->with('lead_status', '已收到，我们会在 1 个工作日内联系您。');
    }
}
