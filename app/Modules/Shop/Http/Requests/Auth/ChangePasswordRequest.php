<?php

namespace App\Modules\Shop\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => '请输入当前密码',
            'password.required' => '请输入新密码',
            'password.confirmed' => '两次输入的新密码不一致',
        ];
    }
}
