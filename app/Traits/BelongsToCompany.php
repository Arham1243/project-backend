<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    /**
     * Prevents Auth::user() from re-entering the company scope while the User model is loading.
     */
    protected static bool $resolvingCompanyScope = false;

    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            if (static::$resolvingCompanyScope) {
                return;
            }

            static::$resolvingCompanyScope = true;

            try {
                $user = Auth::guard('sanctum_admin')->user()
                    ?? Auth::guard('admin')->user();

                if ($user && isset($user->company_id) && $user->company_id) {
                    $builder->where(
                        $builder->getModel()->getTable().'.company_id',
                        $user->company_id
                    );
                }
            } finally {
                static::$resolvingCompanyScope = false;
            }
        });

        static::creating(function ($model) {
            if (! empty($model->company_id)) {
                return;
            }

            $user = Auth::guard('sanctum_admin')->user()
                ?? Auth::guard('admin')->user();

            if ($user && isset($user->company_id) && $user->company_id) {
                $model->company_id = $user->company_id;

                return;
            }

            if (! empty($model->user_id)) {
                $companyId = \App\Modules\Administration\Models\User::withoutGlobalScopes()
                    ->whereKey($model->user_id)
                    ->value('company_id');

                if ($companyId) {
                    $model->company_id = $companyId;
                }
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(\App\Modules\Core\Models\Company::class);
    }
}
