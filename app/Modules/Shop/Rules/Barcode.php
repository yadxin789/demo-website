<?php

namespace App\Modules\Shop\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 商品条码校验：8/12/13 位纯数字按 EAN-8 / UPC-A / EAN-13 校验位校验；
 * 其他值视为内部条码，允许字母数字与 - _，由前端回退 CODE128 渲染。
 */
class Barcode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $barcode = trim((string) $value);
        if ($barcode === '') {
            return;
        }

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $barcode)) {
            $fail('条码只能包含字母、数字、点、横线和下划线');
            return;
        }

        if (ctype_digit($barcode) && in_array(strlen($barcode), [8, 12, 13], true)) {
            if (! $this->checkDigitValid($barcode)) {
                $fail('EAN/UPC 条码校验位不正确，请核对后重新输入');
            }
        }
    }

    private function checkDigitValid(string $digits): bool
    {
        $length = strlen($digits);
        $check = (int) $digits[$length - 1];
        $sum = 0;

        for ($index = $length - 2, $position = 0; $index >= 0; $index--, $position++) {
            $weight = $position % 2 === 0 ? 3 : 1;
            $sum += ((int) $digits[$index]) * $weight;
        }

        return (10 - ($sum % 10)) % 10 === $check;
    }
}
