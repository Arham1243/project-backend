<?php

namespace App\Modules\Administration\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Administration\Http\Requests\Auth\LoginRequest;
use App\Modules\Administration\Http\Resources\AdminLoginResource;
use App\Modules\Administration\Http\Resources\Auth\AdminProfileResource;
use App\Modules\Administration\Models\User;
use App\Modules\Administration\Models\UserDevice;
use App\Modules\Administration\Models\UserLoginOtp;
use App\Modules\Administration\Models\Admin\AdminEmailTemplate;
use App\Modules\Administration\Services\AdminAuthMailer;
use App\Support\AccountStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember_me', false);

        $contactUser = User::where('email', $credentials['email'])->first();

        if (! $contactUser) {
            return response()->json(['message' => 'User Not Found.', 'errors' => ['email' => ['User Not Found.']]], 422);
        }

        if (! Auth::guard('admin')->attempt($credentials)) {
            return response()->json(['message' => 'The provided credentials are invalid.', 'errors' => ['email' => ['The provided credentials are invalid.']]], 422);
        }

        $user = Auth::guard('admin')->user();

        // Handle device verification if required
        $deviceFingerprint = $request->input('device_fingerprint');
        if ($deviceFingerprint) {
            $deviceResponse = $this->handleDeviceVerification($user, $deviceFingerprint);
            if ($deviceResponse) {
                return $deviceResponse;
            }
        }

        if ($blocked = $this->inactiveAdminLoginResponse($user)) {
            return $blocked;
        }

        // Create and return access token
        return $this->createAuthToken($user, $remember);
    }

    /**
     * Handle device verification and OTP flow
     */
    private function handleDeviceVerification(User $user, string $deviceFingerprint)
    {
        $device = UserDevice::where('user_id', $user->id)->where('device_fingerprint', $deviceFingerprint)->where('is_verified', true)->first();

        if (! $device) {
            return $this->sendOtpForDeviceVerification($user, $deviceFingerprint);
        }

        $device->update(['last_used_at' => now()]);

        return null;
    }

    /**
     * Generate and send OTP for device verification
     */
    private function sendOtpForDeviceVerification(User $user, string $deviceFingerprint)
    {
        if ($blocked = $this->inactiveAdminLoginResponse($user)) {
            return $blocked;
        }

        UserLoginOtp::where('user_id', $user->id)->where('device_fingerprint', $deviceFingerprint)->delete();

        $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $sessionToken = Str::random(64);
        $expirationMinutes = UserLoginOtp::expirationMinutes();

        UserLoginOtp::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'device_fingerprint' => $deviceFingerprint,
            'otp_code' => $otpCode,
            'session_token' => $sessionToken,
            'expires_at' => now()->addMinutes($expirationMinutes),
        ]);

        $this->sendOtpEmail($user, $otpCode, $expirationMinutes);

        return response()->json(['message' => 'OTP verification required', 'challenge' => 'OTP_REQUIRED', 'session' => $sessionToken], 200);
    }

    /**
     * Send OTP email to customer contact
     */
    private function sendOtpEmail(User $user, string $otpCode, ?int $expirationMinutes = null): void
    {
        $expirationMinutes ??= UserLoginOtp::expirationMinutes();

        app(AdminAuthMailer::class)->send(
            AdminEmailTemplate::SLUG_LOGIN_OTP,
            $user->email,
            [
                'name' => $user->name ?: $user->email,
                'otp_code' => $otpCode,
                'expires_in_minutes' => (string) $expirationMinutes,
            ],
            $user->id
        );
    }

    /**
     * Inactive internal admins cannot proceed past password check or OTP send.
     */
    private function inactiveAdminLoginResponse(User $user): ?JsonResponse
    {
        if (AccountStatus::isActive($user->status)) {
            return null;
        }

        Auth::guard('admin')->logout();

        $message = 'Your access has been expired. Please contact your System Administrator to restore it.';

        return response()->json([
            'message' => $message,
            'errors' => ['email' => [$message]],
        ], 422);
    }

    /**
     * Create authentication token for customer contact
     */
    private function createAuthToken(User $user, bool $remember)
    {
        $expiration = $remember ? config('sanctum.expiration_long') : config('sanctum.expiration');
        $tokenResult = $user->createToken('auth-token');
        $tokenModel = $tokenResult->accessToken;
        $tokenModel->expires_at = now()->addMinutes($expiration);
        $tokenModel->save();

        $user->access_token = $tokenResult->plainTextToken;
        $user->expires_in = $tokenModel->expires_at->timestamp;

        return new AdminLoginResource($user);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $user->tokens()->where('id', $user->currentAccessToken()->id)->delete();
        }

        return response()->json([
            'status' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $user->loadMissing(['role', 'company']);

        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return response()->json([
            'data' => new AdminProfileResource($user),
            'my_company' => $user->company
                ? new \App\Modules\Administration\Http\Resources\CompanyResource($user->company)
                : null,
            'permissions' => $permissions,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'profile_image' => ['nullable'],
            'timezone' => ['nullable', 'string', 'max:100', 'timezone:all'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => new AdminProfileResource($user->fresh(['role'])),
        ]);
    }

    public function updateUiPreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dark_mode' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update([
            'dark_mode' => $validated['dark_mode'],
        ]);

        return response()->json([
            'data' => [
                'dark_mode' => (bool) $user->dark_mode,
            ],
        ]);
    }

    public function tablePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_key' => ['required', 'string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $all = is_array($user->table_preferences) ? $user->table_preferences : [];
        $entry = $all[$validated['table_key']] ?? [];

        return response()->json([
            'data' => [
                'visible_columns' => $entry['visible_columns'] ?? [],
            ],
        ]);
    }

    public function updateTablePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_key' => ['required', 'string', 'max:120'],
            'visible_columns' => ['required', 'array'],
            'visible_columns.*' => ['string', 'max:120'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $all = is_array($user->table_preferences) ? $user->table_preferences : [];
        $all[$validated['table_key']] = [
            'visible_columns' => array_values($validated['visible_columns']),
        ];
        $user->table_preferences = $all;
        $user->save();

        return response()->json([
            'data' => $all[$validated['table_key']],
        ]);
    }
}
