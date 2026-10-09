<?php

namespace App\Modules\Administration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'street_address_1' => ['nullable', 'string', 'max:255'],
            'street_address_2' => ['nullable', 'string', 'max:255'],
            'street_address_3' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'zip' => ['nullable', 'string', 'max:32'],
            'country' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'string'],
            'timezone' => ['required', 'string', 'max:100', 'timezone:all'],
            'date_format' => [
                'required',
                'string',
                Rule::in(['mm/dd/yy', 'dd/mm/yy', 'yy-mm-dd']),
            ],
        ];
    }
}
