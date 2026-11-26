<?php

namespace App\Modules\Erp\Http\Controllers;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('permissions')->withCount('users')->orderBy('name')->get();
        $permissions = Permission::orderBy('name')->get()->groupBy(function ($item) {
            return explode('.', $item->name)[0] ?? 'other';
        });

        return view('erp::roles.index', compact('roles', 'permissions'));
    }
}
