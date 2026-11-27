<?php

declare(strict_types=1);

namespace App\Modules\Mes\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * 智能制造 MES 的登录用户。
 *
 * 硬约束（HC-11）：五个产品各有自己的 users 表、User 模型、guard。
 * 五个模型类名相同但命名空间不同，互不引用，不做单点登录。
 */
class User extends Authenticatable
{
    use HasRoles;
    use Notifiable;

    protected $connection = 'mes';
    protected $table = 'users';

    /** spatie 角色/权限按 guard 隔离，MES 用自己的 guard（HC-11） */
    protected $guard_name = 'mes';

    protected $fillable = ['name', 'username', 'email', 'employee_no', 'team', 'department_id', 'position_id', 'password', 'status', 'last_login_at'];

    protected $guarded = ['id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** 组织关系（S12-M05）：账号挂部门与岗位 */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }
}