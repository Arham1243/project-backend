<?php

namespace App\Modules\Core\Http\Requests;

use Illuminate\Validation\Rule;
use Orion\Http\Requests\Request;

class StudentRequest extends Request
{
    public function storeRules(): array
    {
        return [
            'student_id' => $this->studentIdRules(),
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function updateRules(): array
    {
        return [
            'student_id' => ['sometimes', ...$this->studentIdRules()],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }

    public function commonRules(): array
    {
        return [
            'status' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function studentIdRules(): array
    {
        $companyId = $this->user()?->company_id;

        $unique = Rule::unique('students', 'student_id');
        if ($companyId) {
            $unique = $unique->where(fn ($query) => $query->where('company_id', $companyId));
        }

        if ($this->route('student')) {
            $unique = $unique->ignore($this->route('student'), 'uuid');
        }

        return ['required', 'string', 'max:64', 'alpha_num', $unique];
    }
}
