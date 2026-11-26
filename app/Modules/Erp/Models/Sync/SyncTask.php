<?php

namespace App\Modules\Erp\Models\Sync;

use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncTask extends \App\Modules\Erp\Models\BaseModel
{
    protected $fillable = ['name', 'target', 'direction', 'data_type', 'cron_expr', 'status', 'last_run_at'];
    protected $casts = ['last_run_at' => 'datetime'];

    public function logs(): HasMany
    {
        return $this->hasMany(SyncLog::class, 'sync_task_id');
    }
}
