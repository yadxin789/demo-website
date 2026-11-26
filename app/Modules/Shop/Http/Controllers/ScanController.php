<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Modules\Shop\Services\Scan\ScanResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanController extends Controller
{
    /**
     * 统一扫码解析接口：商品/库位/序列号/批次/订单/付款码。
     */
    public function resolve(Request $request, ScanResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:2048'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ]);

        try {
            $result = $resolver->resolve($data['code'], isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($result);
    }
}
