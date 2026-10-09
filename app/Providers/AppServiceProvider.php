<?php

namespace App\Providers;

use App\Modules\Administration\Models\Activity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiters();

        Schema::defaultStringLength(191);

        Activity::saving(function (Activity $activity) {
            if ($activity->causer_id === null) {
                $causer = Auth::guard('sanctum_admin')->user()
                    ?? Auth::guard('sanctum')->user();

                if ($causer) {
                    $activity->causer()->associate($causer);
                }
            }

            if ($activity->company_id === null) {
                $tenant = Auth::guard('sanctum_admin')->user()
                    ?? Auth::guard('sanctum')->user();
                if ($tenant?->company_id) {
                    $activity->company_id = $tenant->company_id;
                } elseif ($activity->subject !== null && isset($activity->subject->central_company_id)) {
                    $activity->company_id = $activity->subject->central_company_id;
                } elseif ($activity->subject !== null && isset($activity->subject->company_id)) {
                    $activity->company_id = $activity->subject->company_id;
                }
            }
        });
    }

    private function configureRateLimiters(): void
    {
        $healthPerMinute = (int) env('PUBLIC_HEALTH_PER_MINUTE', 300);

        RateLimiter::for(
            'public-health',
            static fn (Request $request): Limit => Limit::perMinute($healthPerMinute)->by($request->ip())
        );
    }
}
