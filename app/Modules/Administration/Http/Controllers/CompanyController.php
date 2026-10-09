<?php

namespace App\Modules\Administration\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Administration\Http\Requests\CompanyRequest;
use App\Modules\Administration\Http\Resources\CompanyResource;
use App\Modules\Core\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function my(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('company');

        if (! $user->company) {
            abort(404, 'Company not found.');
        }

        return new CompanyResource($user->company);
    }

    public function updateMy(CompanyRequest $request)
    {
        $user = $request->user();
        $user->loadMissing('company');

        /** @var Company|null $company */
        $company = $user->company;
        if (! $company) {
            abort(404, 'Company not found.');
        }

        $company->update($request->validated());

        return new CompanyResource($company->fresh());
    }
}
