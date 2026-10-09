<?php

namespace App\Modules\Administration\Http\Requests;

use Orion\Http\Requests\Request;

class AdminRoleRequest extends Request
{
    public function commonRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'boolean'],
        ];
    }

    public function updateRules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
