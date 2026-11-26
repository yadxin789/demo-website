<?php

declare(strict_types=1);

namespace App\Modules\Erp\Models;

/**
 * 电子制造 ERP 的登录用户入口。
 *
 * S0 用这个类名注册了 erp_users provider；业务代码里的完整实现是
 * App\Modules\Erp\Models\Organization\User（带部门/岗位/租户等关系）。
 * 这里只做一层薄继承，保证请求生命周期里 $request->user() 拿到的是同一个类族，
 * 避免 auth 与业务各自持有两套用户对象（HC-11）。
 */
class User extends Organization\User
{
}