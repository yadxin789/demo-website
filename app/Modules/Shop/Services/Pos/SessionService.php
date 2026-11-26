<?php

namespace App\Modules\Shop\Services\Pos;

use App\Modules\Shop\Exceptions\PosException;
use App\Modules\Shop\Models\AuditLog;
use App\Modules\Shop\Models\PosSession;
use App\Modules\Shop\Models\PosStation;
use App\Modules\Shop\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 收银会话服务：开班登记期初现金；同一终端仅允许一个打开会话；
 * 未开班不能进入收银。交班清点与长短款在 P1-512 处理。
 */
class SessionService
{
    public function __construct(protected AuditLogger $logger)
    {
    }

    /**
     * 为指定终端开班。
     */
    public function open(PosStation $station, int $cashierId, float $openedBalance, ?string $note = null): PosSession
    {
        if (! $station->isActive()) {
            throw new PosException("收银台 {$station->code} 已停用，不能开班");
        }

        $existing = $this->activeSession($station->id);
        if ($existing) {
            throw new PosException("收银台 {$station->code} 已有未交班会话");
        }

        return DB::transaction(function () use ($station, $cashierId, $openedBalance, $note) {
            if ($openedBalance < 0) {
                throw new PosException('期初现金不能为负');
            }

            $session = PosSession::create([
                'station_id' => $station->id,
                'cashier_id' => $cashierId,
                'opened_at' => Carbon::now(),
                'opened_balance' => $openedBalance,
                'status' => PosSession::STATUS_OPEN,
                'note' => $note,
            ]);

            $this->logger->record([
                'module' => 'pos',
                'action' => AuditLog::ACTION_CREATE,
                'model' => PosSession::class,
                'record_id' => $session->id,
                'document_no' => $station->code,
                'message' => "收银台 {$station->code} 开班，期初现金 {$openedBalance}",
                'context' => [
                    'station_id' => $station->id,
                    'cashier_id' => $cashierId,
                    'opened_balance' => $openedBalance,
                ],
            ]);

            return $session;
        });
    }

    /**
     * 取终端当前打开的会话；无则返回 null。
     */
    public function activeSession(int $stationId): ?PosSession
    {
        return PosSession::where('station_id', $stationId)
            ->where('status', PosSession::STATUS_OPEN)
            ->first();
    }

    /**
     * 收银前校验：终端必须存在打开会话，否则拦截。
     */
    public function requireOpenSession(int $stationId): PosSession
    {
        $session = $this->activeSession($stationId);

        if (! $session) {
            throw new PosException('当前收银台尚未开班，不能收银');
        }

        return $session;
    }
}
