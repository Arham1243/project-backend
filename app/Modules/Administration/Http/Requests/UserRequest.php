<?php

namespace App\Modules\Administration\Http\Requests;

use Illuminate\Validation\Rule;
use Orion\Http\Requests\Request;

class UserRequest extends Request
{
    public function storeRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:admin_users,email'],
            'role_id' => ['required', 'exists:admin_roles,id'],
        ];
    }

    public function updateRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('admin_users', 'email')->ignore($this->route('user'), 'uuid'),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['required', 'exists:admin_roles,id'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ];
    }

    public function commonRules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'max:50'],
            'profile_image' => ['nullable'],
            'timezone' => ['nullable', 'string', 'max:100', 'timezone:all'],
        ];
    }
}
