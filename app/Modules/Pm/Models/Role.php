<?php

namespace App\Modules\Pm\Models;

/**
 * 业务角色（自定义 roles 表），与 spatie_roles（权限角色）是两套东西。
 * 用户通过 users.role_id 指向本表，同时用 HasRoles 维护 spatie 角色名。
 */
class Role extends BaseModel
{
    protected $fillable = ['name', 'code', 'description'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
