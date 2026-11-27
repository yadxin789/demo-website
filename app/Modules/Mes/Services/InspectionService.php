<?php

declare(strict_types=1);

namespace App\Modules\Mes\Services;

use App\Modules\Mes\Models\Inspection;
use App\Modules\Mes\Models\InspectionResult;
use Illuminate\Support\Facades\DB;

/**
 * 检验判定（M4-02）。
 *
 * 判定规则按项依次尝试，先命中先用：
 *   1. 实测值为空 → ng（没测就不算合格）
 *   2. 该项配了 min / max → 实测按数值比区间
 *   3. 标准值是纯数字（例如「虚焊数 0」）→ 实测必须等于该数字
 *   4. 配了 expect（合格样本值）→ 与实测做文本比对（去空格、忽略大小写）
 *   5. 以上都不适用 → 视为人工判定项，有实测值即 ok
 *
 * 整体结果：任意一项 ng → 整单 fail；否则 pass。
 */
class InspectionService
{
    /**
     * 逐项判定。
     *
     * @param  array<int, array<string, mixed>>  $items  模板项
     * @param  array<int, string>  $actuals  实测值（下标与模板项对齐）
     * @return array{rows: array<int, array<string, string>>, overall: string, ng: int, ok: int}
     */
    public function judge(array $items, array $actuals): array
    {
        $rows = [];
        $ng = 0;

        foreach ($items as $i => $item) {
            $raw = trim((string) ($actuals[$i] ?? ''));
            $verdict = $this->judgeOne($item, $raw);

            if ($verdict === 'ng') {
                $ng++;
            }

            $rows[] = [
                'name' => (string) ($item['name'] ?? ('项目 ' . ($i + 1))),
                'std' => $this->stdText($item),
                'unit' => (string) ($item['unit'] ?? ''),
                'actual' => $raw,
                'verdict' => $verdict,
            ];
        }

        return [
            'rows' => $rows,
            'overall' => $ng > 0 ? 'fail' : 'pass',
            'ng' => $ng,
            'ok' => count($items) - $ng,
        ];
    }

    /** 单项判定 */
    public function judgeOne(array $item, string $actual): string
    {
        if ($actual === '') {
            return 'ng';
        }

        $hasMin = isset($item['min']) && $item['min'] !== '' && is_numeric($item['min']);
        $hasMax = isset($item['max']) && $item['max'] !== '' && is_numeric($item['max']);

        if ($hasMin || $hasMax) {
            $num = $this->toNumber($actual);
            if ($num === null) {
                return 'ng';
            }
            if ($hasMin && $num < (float) $item['min']) {
                return 'ng';
            }
            if ($hasMax && $num > (float) $item['max']) {
                return 'ng';
            }

            return 'ok';
        }

        $std = trim((string) ($item['std'] ?? ''));
        if (is_numeric($std)) {
            $num = $this->toNumber($actual);

            return ($num !== null && abs($num - (float) $std) < 0.000001) ? 'ok' : 'ng';
        }

        $expect = trim((string) ($item['expect'] ?? ''));
        if ($expect !== '') {
            return $this->normalize($actual) === $this->normalize($expect) ? 'ok' : 'ng';
        }

        // 没有可自动判定的标准：记录实测值，按人工判定为合格
        return 'ok';
    }

    /**
     * 提交检验结果：写明细 + 定整体结果。
     *
     * @param  array<int, string>  $actuals
     * @return array{overall: string, ng: int, ok: int}
     */
    public function submit(Inspection $inspection, array $actuals, ?int $inspectorId): array
    {
        $items = (array) ($inspection->template?->items ?? []);
        $verdict = $this->judge($items, $actuals);

        DB::connection('mes')->transaction(function () use ($inspection, $verdict, $inspectorId) {
            InspectionResult::query()->where('inspection_id', $inspection->id)->delete();

            foreach ($verdict['rows'] as $row) {
                InspectionResult::query()->create([
                    'inspection_id' => $inspection->id,
                    'item_name' => $row['name'],
                    'std_value' => $row['std'],
                    'actual_value' => $row['actual'],
                    'verdict' => $row['verdict'],
                ]);
            }

            $inspection->update([
                'result' => $verdict['overall'],
                'inspector_id' => $inspectorId,
                'inspected_at' => now(),
            ]);
        });

        return ['overall' => $verdict['overall'], 'ng' => $verdict['ng'], 'ok' => $verdict['ok']];
    }

    /** 模板项的"标准"展示文本：区间优先。 */
    public function stdText(array $item): string
    {
        $min = $item['min'] ?? null;
        $max = $item['max'] ?? null;
        $hasMin = $min !== null && $min !== '' && is_numeric($min);
        $hasMax = $max !== null && $max !== '' && is_numeric($max);

        if ($hasMin && $hasMax) {
            return "{$min} ~ {$max}";
        }
        if ($hasMin) {
            return "≥ {$min}";
        }
        if ($hasMax) {
            return "≤ {$max}";
        }

        return trim((string) ($item['std'] ?? '')) ?: '—';
    }

    /** QC-2026-0001 格式，按年自增 */
    public function nextInspectionNo(): string
    {
        $prefix = 'QC-' . now()->format('Y') . '-';
        $last = Inspection::query()
            ->where('inspection_no', 'like', $prefix . '%')
            ->orderByDesc('inspection_no')
            ->value('inspection_no');

        $tail = $last ? (int) substr((string) $last, -4) : 0;

        return $prefix . str_pad((string) ($tail + 1), 4, '0', STR_PAD_LEFT);
    }

    private function toNumber(string $value): ?float
    {
        $clean = str_replace([',', '，', ' '], '', $value);

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', $value) ?? $value);
    }
}
