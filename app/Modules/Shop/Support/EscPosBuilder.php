<?php

namespace App\Modules\Shop\Support;

/**
 * ESC/POS 小票指令生成器（离线，不依赖网络与第三方库）。
 *
 * 按终端编码（GBK / UTF-8）把文本转换为打印机字节，输出标准 ESC/POS 指令序列：
 * 初始化、对齐、加粗、分隔线、商品行、合计/折扣/支付行、切纸。
 * `bytes()` 返回可直接下发打印机的二进制指令；`text()` 返回去控制符的可读预览。
 */
class EscPosBuilder
{
    private const ESC = "\x1B";
    private const GS = "\x1D";
    private const LF = "\x0A";

    private string $encoding;
    private int $columns;

    /** @var array<int, string> 二进制指令片段 */
    private array $buffer = [];

    /** @var array<int, string> 可读文本行（UTF-8） */
    private array $textLines = [];

    public function __construct(string $encoding = 'gbk', int $columns = 32)
    {
        $this->encoding = in_array(strtolower($encoding), ['gbk', 'gb2312', 'utf-8'], true)
            ? strtolower($encoding)
            : 'gbk';
        $this->columns = $columns > 0 ? $columns : 32;
    }

    /** 打印机初始化（复位格式与缓冲）。 */
    public function init(): self
    {
        $this->buffer[] = self::ESC . '@';

        return $this;
    }

    /** 对齐方式：left / center / right。 */
    public function align(string $mode): self
    {
        $map = ['left' => 0, 'center' => 1, 'right' => 2];
        $n = $map[$mode] ?? 0;
        $this->buffer[] = self::ESC . 'a' . chr($n);

        return $this;
    }

    /** 加粗开关。 */
    public function bold(bool $on): self
    {
        $this->buffer[] = self::ESC . 'E' . chr($on ? 1 : 0);

        return $this;
    }

    /** 追加一行文本（自动换行）。 */
    public function line(string $text = ''): self
    {
        $this->buffer[] = $this->encode($text) . self::LF;
        $this->textLines[] = $text;

        return $this;
    }

    /** 空行。 */
    public function blank(): self
    {
        return $this->line('');
    }

    /** 分隔线。 */
    public function rule(): self
    {
        return $this->line(str_repeat('-', $this->columns));
    }

    /** 居中标题行。 */
    public function heading(string $text, bool $bold = true): self
    {
        $this->align('center')->bold($bold)->line($text)->bold(false)->align('left');

        return $this;
    }

    /** 标签:值 行（左标签、右值）。 */
    public function metaRow(string $label, string $value): self
    {
        $line = $this->padRight($label, $this->columns - max(1, $this->strWidth($value))) . $value;

        return $this->line($line);
    }

    /**
     * 商品行：名称 + 编码，第二行 数量 × 单价 与金额。
     */
    public function itemLine(string $name, float $qty, float $price, float $amount, ?string $code = null): self
    {
        $title = $code ? "{$name}（{$code}）" : $name;
        $this->line($this->truncate($title, $this->columns));

        $left = number_format($qty, 2, '.', '') . ' × ' . number_format($price, 2, '.', '');
        $right = $this->money($amount);
        $this->line($this->padRight($left, $this->columns - max(1, $this->strWidth($right))) . $right);

        return $this;
    }

    /** 合计/折扣/税额行（左标签、右金额，可加粗）。 */
    public function amountRow(string $label, float $amount, bool $bold = false): self
    {
        $value = $this->money($amount);
        $line = $this->padRight($label, $this->columns - max(1, $this->strWidth($value))) . $value;

        if ($bold) {
            $this->bold(true)->line($line)->bold(false);
        } else {
            $this->line($line);
        }

        return $this;
    }

    /** 支付方式行：方式、金额、参考号。 */
    public function paymentRow(string $method, float $amount, ?string $reference = null): self
    {
        $value = $this->money($amount);
        $line = $this->padRight($method, $this->columns - max(1, $this->strWidth($value))) . $value;
        $this->line($line);

        if ($reference) {
            $this->line('  单号：' . $reference);
        }

        return $this;
    }

    /** 切纸（GS V 0）。 */
    public function cut(): self
    {
        $this->buffer[] = self::GS . 'V' . chr(0);

        return $this;
    }

    /**
     * CODE39 条码（GS h 高度 / GS w 宽度 / GS k m=4）。
     * 仅保留 CODE39 可编码字符，无法编码时直接跳过，避免打印机输出乱码。
     */
    public function barcode(string $data, int $height = 60, int $width = 2): self
    {
        $clean = (string) preg_replace('/[^0-9A-Za-z\-\.\ \$\/\+\%]/', '', $data);

        if ($clean === '') {
            return $this;
        }

        $this->align('center');
        $this->buffer[] = self::GS . 'h' . chr(max(1, min(255, $height)));
        $this->buffer[] = self::GS . 'w' . chr(max(2, min(6, $width)));
        $this->buffer[] = self::GS . 'k' . chr(4) . $clean . chr(0);
        $this->textLines[] = '[条码] ' . $clean;
        $this->align('left');

        return $this;
    }

    /**
     * QR 二维码（GS ( k，型号 2）。错误纠正等级 L/M/Q/H，模块大小 1-16。
     */
    public function qrCode(string $data, int $size = 4, string $errorCorrection = 'M'): self
    {
        if ($data === '') {
            return $this;
        }

        $levels = ['L' => 48, 'M' => 49, 'Q' => 50, 'H' => 51];
        $level = $levels[strtoupper($errorCorrection)] ?? 49;
        $size = max(1, min(16, $size));
        $payload = $this->encode($data);
        $length = strlen($payload) + 2;

        $this->align('center');
        // 选择型号 2
        $this->buffer[] = self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(65) . chr(50);
        // 模块大小
        $this->buffer[] = self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(67) . chr($size);
        // 错误纠正等级
        $this->buffer[] = self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(69) . chr($level);
        // 存储数据
        $this->buffer[] = self::GS . '(k' . chr($length % 256) . chr(intdiv($length, 256))
            . chr(49) . chr(80) . $payload;
        // 打印
        $this->buffer[] = self::GS . '(k' . chr(3) . chr(0) . chr(49) . chr(81) . chr(48);
        $this->textLines[] = '[二维码] ' . $data;
        $this->align('left');

        return $this;
    }

    /**
     * 一次性组装完整小票。
     *
     * @param  array<string, mixed>  $receipt
     */
    public function build(array $receipt): self
    {
        $this->init();

        $this->heading((string) ($receipt['store_name'] ?? '收银小票'));
        if (! empty($receipt['address'])) {
            $this->align('center')->line((string) $receipt['address']);
        }
        if (! empty($receipt['phone'])) {
            $this->align('center')->line('电话：' . $receipt['phone']);
        }
        $this->align('left')->rule();

        if (! empty($receipt['order_code'])) {
            $this->metaRow('单号', (string) $receipt['order_code']);
        }
        if (! empty($receipt['order_date'])) {
            $this->metaRow('时间', (string) $receipt['order_date']);
        }
        if (! empty($receipt['cashier']) && ($receipt['show_cashier'] ?? true)) {
            $this->metaRow('收银员', (string) $receipt['cashier']);
        }
        if (! empty($receipt['customer'])) {
            $this->metaRow('客户', (string) $receipt['customer']);
        }
        if (($receipt['show_barcode'] ?? false) && ! empty($receipt['order_code'])) {
            $this->barcode((string) $receipt['order_code']);
            $this->qrCode('ERP-ORDER:' . (string) $receipt['order_code']);
        }
        $this->rule();

        foreach (($receipt['items'] ?? []) as $item) {
            $this->itemLine(
                (string) ($item['name'] ?? ''),
                (float) ($item['qty'] ?? 0),
                (float) ($item['price'] ?? 0),
                (float) ($item['amount'] ?? 0),
                $item['code'] ?? null
            );
        }

        $this->rule();

        if (! empty($receipt['discount']) && ($receipt['show_discount'] ?? true)) {
            $this->amountRow('折扣', (float) $receipt['discount']);
        }
        if (isset($receipt['tax'])) {
            $this->amountRow('税额', (float) $receipt['tax']);
        }
        $this->amountRow('应收合计', (float) ($receipt['total'] ?? 0), true);

        foreach (($receipt['payments'] ?? []) as $payment) {
            $this->paymentRow(
                (string) ($payment['method'] ?? '其他'),
                (float) ($payment['amount'] ?? 0),
                $payment['reference'] ?? null
            );
        }

        if (isset($receipt['change'])) {
            $this->amountRow('找零', (float) $receipt['change']);
        }

        $this->rule();
        $this->align('center')->line((string) ($receipt['footer'] ?? '谢谢惠顾，欢迎再次光临'));
        $this->align('left');
        $this->cut();

        return $this;
    }

    /** 二进制 ESC/POS 指令（按终端编码）。 */
    public function bytes(): string
    {
        return implode('', $this->buffer);
    }

    /** 去控制符的可读纯文本预览（UTF-8）。 */
    public function text(): string
    {
        return implode(self::LF, $this->textLines);
    }

    private function encode(string $text): string
    {
        if ($this->encoding === 'utf-8') {
            return $text;
        }

        return mb_convert_encoding($text, $this->encoding, 'UTF-8');
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /** 粗略显示宽度：中文/全角计 2，其余计 1。 */
    private function strWidth(string $text): int
    {
        $width = 0;
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $width += (mb_strlen($char, 'UTF-8') > 1 || preg_match('/[^\x00-\x7F]/', $char)) ? 2 : 1;
        }

        return $width;
    }

    private function padRight(string $text, int $width): string
    {
        $gap = $width - $this->strWidth($text);

        return $gap > 0 ? $text . str_repeat(' ', $gap) : $text;
    }

    private function truncate(string $text, int $maxWidth): string
    {
        if ($this->strWidth($text) <= $maxWidth) {
            return $text;
        }

        $result = '';
        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            if ($this->strWidth($result . $char) > $maxWidth - 1) {
                break;
            }
            $result .= $char;
        }

        return $result . '…';
    }
}
