<?php

namespace App\Modules\Shop\Ai\Tools;

use App\Modules\Shop\Ai\Concerns\ErpFormats;
use App\Modules\Shop\Models\Bill;
use App\Modules\Shop\Models\Invoice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class QueryFinance implements Tool
{
    use ErpFormats;

    public function description(): Stringable|string
    {
        return '查询应收应付与账龄：返回未收/未付余额、逾期金额，以及逾期客户或供应商明细。';
    }

    public function handle(Request $request): Stringable|string
    {
        $type = (string) ($request['type'] ?? 'all');
        $limit = $this->limit($request['limit'] ?? null, 10, 30);
        $parts = [];

        if (in_array($type, ['all', 'receivable'], true)) {
            $openInvoices = Invoice::with('partner')
                ->whereIn('status', [Invoice::STATUS_OPEN, Invoice::STATUS_PARTIAL])
                ->orderBy('due_date')
                ->get();

            $total = $openInvoices->sum(fn (Invoice $i) => $i->balance());
            $overdue = $openInvoices->filter(fn (Invoice $i) => $i->due_date && $i->due_date->isPast())->sum(
                fn (Invoice $i) => $i->balance()
            );

            $parts[] = '应收：未收 ' . $this->money($total)
                . '，其中逾期 ' . $this->money($overdue)
                . '；共 ' . $openInvoices->count() . ' 张未结发票。';

            $overdueList = $openInvoices
                ->filter(fn (Invoice $i) => $i->due_date && $i->due_date->isPast())
                ->take($limit);
            foreach ($overdueList as $invoice) {
                $parts[] = '- 发票 ' . $invoice->code
                    . '｜客户 ' . ($invoice->partner?->name ?: '—')
                    . '｜到期 ' . $this->date($invoice->due_date)
                    . '｜逾期 ' . max(0, (int) $invoice->due_date->diffInDays(now()))
                    . ' 天｜未收 ' . $this->money($invoice->balance());
            }
        }

        if (in_array($type, ['all', 'payable'], true)) {
            $openBills = Bill::with('partner')
                ->whereIn('status', [Bill::STATUS_OPEN, Bill::STATUS_PARTIAL])
                ->orderBy('due_date')
                ->get();

            $total = $openBills->sum(fn (Bill $b) => $b->balance());
            $overdue = $openBills->filter(fn (Bill $b) => $b->due_date && $b->due_date->isPast())->sum(
                fn (Bill $b) => $b->balance()
            );

            $parts[] = '应付：未付 ' . $this->money($total)
                . '，其中逾期 ' . $this->money($overdue)
                . '；共 ' . $openBills->count() . ' 张未结账单。';

            $overdueList = $openBills
                ->filter(fn (Bill $b) => $b->due_date && $b->due_date->isPast())
                ->take($limit);
            foreach ($overdueList as $bill) {
                $parts[] = '- 账单 ' . $bill->code
                    . '｜供应商 ' . ($bill->partner?->name ?: '—')
                    . '｜到期 ' . $this->date($bill->due_date)
                    . '｜逾期 ' . max(0, (int) $bill->due_date->diffInDays(now()))
                    . ' 天｜未付 ' . $this->money($bill->balance());
            }
        }

        return implode("\n", $parts) ?: '没有可用的应收应付数据。';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('all / receivable / payable'),
            'limit' => $schema->integer()->min(1)->max(30),
        ];
    }
}
