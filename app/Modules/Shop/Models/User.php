<?php

namespace App\Modules\Shop\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{

    protected $connection = 'shop';

    protected $guard_name = 'shop';

    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'avatar',
        'phone',
        'password',
        'status',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * 用户拥有的全部权限 code（经角色聚合）。
     *
     * @return list<string>
     */
    public function permissionCodes(): array
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('code'))
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $code): bool
    {
        return in_array($code, $this->permissionCodes(), true);
    }

    public function isAdmin(): bool
    {
        return $this->roles()->where('code', 'super-admin')->exists();
    }

    public function canManageAi(): bool
    {
        return $this->isAdmin() || $this->hasPermission('system.ai.manage');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
