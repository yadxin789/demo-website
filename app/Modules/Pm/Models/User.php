<?php

declare(strict_types=1);

namespace App\Modules\Pm\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * 研发项目管理 的登录用户。
 *
 * 硬约束（HC-11）：五个产品各有自己的 users 表、User 模型、guard。
 * 五个模型类名相同但命名空间不同，互不引用，不做单点登录。
 */
class User extends Authenticatable
{
    use HasRoles;
    use Notifiable;

    protected $connection = 'pm';
    protected $table = 'users';

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
}