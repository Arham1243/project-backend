<?php

namespace App\Modules\Administration\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Administration\Http\Resources\AdminLoginResource;
use App\Modules\Administration\Models\User;
use App\Modules\Administration\Models\Admin\AdminEmailTemplate;
use App\Modules\Administration\Services\AdminAuthMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
                function ($attribute, $value, $fail) {
                    $contactExists = User::where('email', $value)->exists();
                    if (! $contactExists) {
                        $fail('Please contact your Administrator for further details.');
                    }
                },
            ],
        ]);

        $email = $request->input('email');

        $user = User::where('email', $email)->first();

        $token = Str::random(64);

        DB::table('admin_password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => $token,
                'created_at' => now(),
            ]
        );

        $verifyLink = rtrim((string) config('app.frontend_url'), '/')
            .'/auth/password/reset?token='.urlencode($token);

        app(AdminAuthMailer::class)->send(
            AdminEmailTemplate::SLUG_RESET_PASSWORD,
            $email,
            [
                'name' => $user->name ?: $email,
                'verify_link' => $verifyLink,
            ],
            $user->id
        );

        return response()->json([
            'status' => true,
            'message' => 'Password reset link sent to your email.',
        ]);
    }

    public function setupPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $record = DB::table('admin_password_reset_tokens')
            ->where('token', $request->token)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'Session expired',
                'errors' => ['email' => ['Session expired']],
            ], 422);
        }

        $admin = User::where('email', $record->email)->first();

        if (! $admin) {
            return response()->json([
                'message' => 'User Not Found.',
                'errors' => ['email' => ['User Not Found.']],
            ], 422);
        }

        $admin->update([
            'password' => Hash::make($request->password),
            'status' => 'active',
        ]);

        DB::table('admin_password_reset_tokens')->where('email', $record->email)->delete();

        $expiration = config('sanctum.expiration');
        $tokenResult = $admin->createToken('auth-token');
        $tokenModel = $tokenResult->accessToken;
        $tokenModel->expires_at = now()->addMinutes($expiration);
        $tokenModel->save();

        $admin->access_token = $tokenResult->plainTextToken;
        $admin->expires_in = $tokenModel->expires_at->timestamp;

        return new AdminLoginResource($admin);
    }

    public function resetPassword(
        Request $request,
    ) {
        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $data = $request->only('token', 'password');

        $record = DB::table('admin_password_reset_tokens')
            ->where('token', $request->token)
            ->first();

        if (! $record) {
            return response()->json([
                'message' => 'Session expired',
                'errors' => ['email' => ['Session expired']],
            ], 422);
        }

        $contact = User::where('email', $record->email)->first();

        if (! $contact) {
            return response()->json(['message' => 'User Not Found.', 'errors' => ['email' => ['User Not Found.']]], 422);
        }

        $contact->update(['password' => Hash::make($data['password'])]);

        // remove the token
        DB::table('admin_password_reset_tokens')->where('email', $record->email)->delete();

        return response()->json(['status' => true, 'message' => 'Password reset successful.']);
    }

}
