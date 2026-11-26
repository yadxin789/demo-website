<?php

namespace App\Modules\Shop\Services\Exchange;

/**
 * 导入结果：成功条数 + 逐行错误（行号可直接对应 Excel 行）。
 */
final class ImportResult
{
    /**
     * @param  array<int, array{line: int, message: string}>  $errors
     */
    public function __construct(
        public readonly int $imported,
        public readonly array $errors = [],
    ) {
    }

    public function failed(): bool
    {
        return $this->errors !== [];
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }
}
