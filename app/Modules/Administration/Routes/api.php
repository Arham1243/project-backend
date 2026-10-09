<?php

use App\Modules\Administration\Http\Controllers\AdminEmailTemplateController;
use App\Modules\Administration\Http\Controllers\CompanyController;
use App\Modules\Administration\Http\Controllers\AdminRoleController;
use App\Modules\Administration\Http\Controllers\AdminRolePermissionController;
use App\Modules\Administration\Http\Controllers\Auth\AuthController;
use App\Modules\Administration\Http\Controllers\Auth\OtpController;
use App\Modules\Administration\Http\Controllers\Auth\PasswordResetController;
use App\Modules\Administration\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Orion\Facades\Orion;

Route::middleware(['auth.any:sanctum_admin', 'check.active'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/ui-preferences', [AuthController::class, 'updateUiPreferences']);
    Route::get('/auth/table-preferences', [AuthController::class, 'tablePreferences']);
    Route::put('/auth/table-preferences', [AuthController::class, 'updateTablePreferences']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum_admin');

    Route::post('/password/forgot', [PasswordResetController::class, 'forgotPassword']);
    Route::post('/password/set', [PasswordResetController::class, 'setupPassword']);
    Route::post('/password/reset', [PasswordResetController::class, 'resetPassword']);

    Route::post('/otp/verify', [OtpController::class, 'verifyOtp']);
    Route::post('/otp/resend', [OtpController::class, 'resendOtp']);
});

Route::middleware(['auth.any:sanctum_admin', 'check.active'])->group(function () {
    Route::middleware(['role.permission:administration.company.view'])->group(function () {
        Route::get('companies/my', [CompanyController::class, 'my']);
    });
    Route::middleware(['role.permission:administration.company.edit'])->group(function () {
        Route::patch('companies/my', [CompanyController::class, 'updateMy']);
    });

    Orion::resource('roles', AdminRoleController::class);
    Route::post('roles/{role}/change-status', [AdminRoleController::class, 'changeStatus']);
    Route::delete('roles/{role}/delete', [AdminRoleController::class, 'deleteRole']);
    Route::get('roles/{role}/permissions', [AdminRolePermissionController::class, 'show']);
    Route::put('roles/{role}/permissions', [AdminRolePermissionController::class, 'sync']);

    Orion::resource('users', UserController::class);
    Route::post('users/{user}/change-status', [UserController::class, 'changeStatus']);
    Route::delete('users/{user}/delete', [UserController::class, 'deleteUser']);

    Route::middleware(['role.permission:administration.email-templates.view'])->group(function () {
        Route::get('email-templates', [AdminEmailTemplateController::class, 'index']);
        Route::post('email-templates/search', [AdminEmailTemplateController::class, 'search']);
        Route::get('email-templates/{adminEmailTemplate}', [AdminEmailTemplateController::class, 'show']);
    });

    Route::middleware(['role.permission:administration.email-templates.edit'])->group(function () {
        Route::put('email-templates/{adminEmailTemplate}', [AdminEmailTemplateController::class, 'update']);
        Route::patch('email-templates/{adminEmailTemplate}', [AdminEmailTemplateController::class, 'update']);
        Route::post('email-templates/{adminEmailTemplate}/change-status', [AdminEmailTemplateController::class, 'changeStatus']);
        Route::post('email-templates/{adminEmailTemplate}/preview', [AdminEmailTemplateController::class, 'preview']);
        Route::post('email-templates/{adminEmailTemplate}/send-test', [AdminEmailTemplateController::class, 'sendTest']);
    });
});
