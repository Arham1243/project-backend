<?php

namespace App\Modules\Administration\Http\Middleware;

use App\Support\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckIfUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $status = $user->status;

            if (! AccountStatus::isActive($status)) {
                optional($request->user()->currentAccessToken())->delete();

                Log::warning('auth.check_active: rejected request (inactive user) - token revoked; common after impersonation if tenant user is not active', [
                    'authenticatable' => $user::class,
                    'user_id' => $user->id,
                    'email' => $user->email ?? null,
                    'status' => $status,
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);

                $message = 'Your access has been expired. Please contact your System Administrator to restore it.';

                return response()->json([
                    'message' => $message,
                    'errors' => ['email' => [$message]],
                ], 403);
            }
        }

        return $next($request);
    }
}
