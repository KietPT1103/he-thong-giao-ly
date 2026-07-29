<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminAccountController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminDirectoryController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\MfaController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('web')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:6,1');
    Route::post('reset-password', [AuthController::class, 'reset'])->middleware('throttle:6,1');
    Route::post('mfa-challenge', [AuthController::class, 'mfaChallenge'])->middleware('throttle:mfa');

    Route::middleware(['auth:sanctum', 'session.absolute'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('confirm-password', [AuthController::class, 'confirmPassword'])->middleware('throttle:5,1');
        Route::get('sessions', [AuthController::class, 'sessions']);

        Route::middleware('password.recent')->group(function () {
            Route::post('change-password', [AuthController::class, 'change'])->middleware('throttle:sensitive');
            Route::delete('sessions/others', [AuthController::class, 'destroyOtherSessions']);
            Route::delete('sessions/{session}', [AuthController::class, 'destroySession']);
        });
    });
});

Route::middleware(['web', 'auth:sanctum', 'session.absolute'])->group(function () {
    Route::get('account', [AccountController::class, 'show']);
    Route::patch('account', [AccountController::class, 'update']);
    Route::post('account/avatar', [AccountController::class, 'avatar'])->middleware('throttle:upload');

    Route::prefix('account/mfa')->middleware(['can:access-admin'])->group(function () {
        Route::get('/', [MfaController::class, 'show']);
        Route::middleware(['password.recent', 'throttle:sensitive'])->group(function () {
            Route::post('setup', [MfaController::class, 'setup']);
            Route::post('confirm', [MfaController::class, 'confirm']);
            Route::post('recovery-codes', [MfaController::class, 'recoveryCodes']);
            Route::delete('/', [MfaController::class, 'destroy']);
        });
    });

    Route::prefix('admin/accounts')->middleware(['can:access-admin', 'can:manage-users'])->group(function () {
        Route::get('/', [AdminAccountController::class, 'index']);
        Route::get('options', [AdminAccountController::class, 'options']);
        Route::get('{account}', [AdminAccountController::class, 'show']);
        Route::patch('{account}', [AdminAccountController::class, 'update']);
        Route::middleware(['password.recent', 'throttle:sensitive'])->group(function () {
            Route::post('/', [AdminAccountController::class, 'store']);
            Route::patch('{account}/status', [AdminAccountController::class, 'status']);
            Route::put('{account}/access', [AdminAccountController::class, 'access']);
            Route::put('{account}/password', [AdminAccountController::class, 'password']);
            Route::delete('{account}', [AdminAccountController::class, 'destroy']);
            Route::post('{account}/restore', [AdminAccountController::class, 'restore']);
        });
    });

    Route::get('admin/dashboard', AdminDashboardController::class);
    Route::prefix('admin')->group(function () {
        Route::get('parishes', [AdminDirectoryController::class, 'parishes']);
        Route::get('teachers', [AdminDirectoryController::class, 'teachers']);
        Route::get('parents', [AdminDirectoryController::class, 'parents']);
        Route::get('children', [AdminDirectoryController::class, 'children']);
        Route::get('classes', [AdminDirectoryController::class, 'classes']);
        Route::get('announcements', [AdminDirectoryController::class, 'announcements']);
    });
    Route::get('teacher/dashboard', [TeacherController::class, 'dashboard']);
    Route::get('teachers/me/classes', [TeacherController::class, 'classes'])->middleware('can:view-classes');
    Route::get('classes/{class}', [ClassController::class, 'show']);
    Route::get('classes/{class}/children', [ClassController::class, 'children']);
    Route::get('classes/{class}/attendance-sessions', [AttendanceController::class, 'index']);
    Route::post('classes/{class}/attendance-sessions', [AttendanceController::class, 'store']);
    Route::get('attendance-sessions/{session}', [AttendanceController::class, 'show']);
    Route::post('attendance-sessions/{session}/mark', [AttendanceController::class, 'mark']);
    Route::post('attendance-sessions/{session}/mark-all-present', [AttendanceController::class, 'markAll']);
    Route::get('attendance-sessions/{session}/summary', [AttendanceController::class, 'summary']);
});

if (app()->environment('testing')) {
    Route::get('security-test-error', fn () => throw new RuntimeException('database-secret-detail'));
}

Route::any('{path}', fn () => response()->json([
    'success' => false,
    'message' => 'Không tìm thấy tài nguyên.',
    'code' => 'NOT_FOUND',
], 404))->where('path', '.*');
