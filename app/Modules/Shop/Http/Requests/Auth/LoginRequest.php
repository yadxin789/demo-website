<?php

namespace App\Modules\Shop\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => '请输入用户名或邮箱',
            'password.required' => '请输入密码',
            'password.min' => '密码至少 6 位',
        ];
    }
}
