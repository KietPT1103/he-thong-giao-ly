<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminAccountController;
use App\Http\Controllers\Api\AdminChildController;
use App\Http\Controllers\Api\AdminClassCatalogController;
use App\Http\Controllers\Api\AdminClassController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminDirectoryController;
use App\Http\Controllers\Api\AdminParentController;
use App\Http\Controllers\Api\AdminParishController;
use App\Http\Controllers\Api\AdminTeacherController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChildAssignmentController;
use App\Http\Controllers\Api\ChildDeviceController;
use App\Http\Controllers\Api\ChildScheduleController;
use App\Http\Controllers\Api\ClassController;
use App\Http\Controllers\Api\LearningFileController;
use App\Http\Controllers\Api\NotificationInboxController;
use App\Http\Controllers\Api\QrAttendanceController;
use App\Http\Controllers\Api\TeacherAnnouncementController;
use App\Http\Controllers\Api\TeacherAssignmentController;
use App\Http\Controllers\Api\TeacherAssignmentReportController;
use App\Http\Controllers\Api\TeacherClassController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\TeacherGradingController;
use App\Http\Controllers\Api\TeacherQuestionBankController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->middleware('web')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:6,1');
    Route::post('reset-password', [AuthController::class, 'reset'])->middleware('throttle:6,1');

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
    Route::get('avatars/{user}', [AccountController::class, 'avatarFile']);

    Route::prefix('child-device')->middleware('can:check-in-attendance-qr')->group(function () {
        Route::get('/', [ChildDeviceController::class, 'show']);
        Route::post('/', [ChildDeviceController::class, 'store'])->middleware('throttle:sensitive');
        Route::delete('/', [ChildDeviceController::class, 'destroy'])->middleware('throttle:sensitive');
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
        Route::prefix('parishes')
            ->middleware(['can:access-admin', 'can:manage-system-settings'])
            ->group(function () {
                Route::get('/', [AdminParishController::class, 'index']);
                Route::post('/', [AdminParishController::class, 'store']);
                Route::get('{parish}', [AdminParishController::class, 'show']);
                Route::patch('{parish}', [AdminParishController::class, 'update']);
                Route::put('{parish}/teachers', [AdminParishController::class, 'assignTeachers']);
                Route::delete('{parish}', [AdminParishController::class, 'destroy']);
            });
        Route::prefix('teachers')
            ->middleware(['can:access-admin', 'can:manage-users'])
            ->group(function () {
                Route::get('/', [AdminTeacherController::class, 'index']);
                Route::post('/', [AdminTeacherController::class, 'store']);
                Route::get('{teacher}', [AdminTeacherController::class, 'show']);
                Route::patch('{teacher}', [AdminTeacherController::class, 'update']);
                Route::delete('{teacher}', [AdminTeacherController::class, 'destroy']);
                Route::post('{teacher}/restore', [AdminTeacherController::class, 'restore']);
            });
        Route::middleware('can:access-admin')->group(function () {
            Route::get('class-catalogs', [AdminClassCatalogController::class, 'index'])
                ->middleware(['can:view-academic-years', 'can:view-levels', 'can:view-classrooms']);

            Route::post('academic-years', [AdminClassCatalogController::class, 'storeAcademicYear'])
                ->middleware('can:create-academic-years');
            Route::patch('academic-years/{academic_year}', [AdminClassCatalogController::class, 'updateAcademicYear'])
                ->middleware('can:update-academic-years');
            Route::delete('academic-years/{academic_year}', [AdminClassCatalogController::class, 'destroyAcademicYear'])
                ->middleware('can:delete-academic-years');

            Route::post('catechism-levels', [AdminClassCatalogController::class, 'storeLevel'])
                ->middleware('can:create-levels');
            Route::patch('catechism-levels/{catechism_level}', [AdminClassCatalogController::class, 'updateLevel'])
                ->middleware('can:update-levels');
            Route::delete('catechism-levels/{catechism_level}', [AdminClassCatalogController::class, 'destroyLevel'])
                ->middleware('can:delete-levels');

            Route::post('classrooms', [AdminClassCatalogController::class, 'storeClassroom'])
                ->middleware('can:create-classrooms');
            Route::patch('classrooms/{classroom}', [AdminClassCatalogController::class, 'updateClassroom'])
                ->middleware('can:update-classrooms');
            Route::delete('classrooms/{classroom}', [AdminClassCatalogController::class, 'destroyClassroom'])
                ->middleware('can:delete-classrooms');
        });
        Route::prefix('classes')
            ->middleware('can:access-admin')
            ->group(function () {
                Route::get('/', [AdminClassController::class, 'index']);
                Route::post('/', [AdminClassController::class, 'store'])->middleware('can:create-classes');
                Route::get('options', [AdminClassController::class, 'options'])->middleware('can:view-classes');
                Route::get('{class}', [AdminClassController::class, 'show'])->middleware('can:view-classes');
                Route::patch('{class}', [AdminClassController::class, 'update'])->middleware('can:update-classes');
                Route::put('{class}/teachers', [AdminClassController::class, 'assignTeachers'])->middleware('can:assign-teachers');
                Route::put('{class}/enrollments', [AdminClassController::class, 'updateEnrollments'])->middleware('can:enroll-children');
                Route::put('{class}/schedules', [AdminClassController::class, 'updateSchedules'])->middleware('can:update-classes');
                Route::delete('{class}', [AdminClassController::class, 'destroy'])->middleware('can:delete-classes');
                Route::post('{class}/restore', [AdminClassController::class, 'restore'])->middleware('can:delete-classes');
            });
        Route::prefix('parents')->middleware('can:access-admin')->group(function () {
            Route::get('/', [AdminParentController::class, 'index'])->middleware('can:view-parents');
            Route::get('options', [AdminParentController::class, 'options'])->middleware('can:view-parents');
            Route::post('/', [AdminParentController::class, 'store'])->middleware('can:create-parents');
            Route::get('{parent}', [AdminParentController::class, 'show'])->middleware('can:view-parents');
            Route::patch('{parent}', [AdminParentController::class, 'update'])->middleware('can:update-parents');
            Route::delete('{parent}', [AdminParentController::class, 'destroy'])
                ->middleware(['can:manage-users', 'password.recent', 'throttle:sensitive']);
            Route::post('{parent}/restore', [AdminParentController::class, 'restore'])->middleware('can:manage-users');
        });
        Route::prefix('children')->middleware('can:access-admin')->group(function () {
            Route::get('/', [AdminChildController::class, 'index'])->middleware('can:view-children');
            Route::get('options', [AdminChildController::class, 'options'])->middleware('can:view-children');
            Route::post('/', [AdminChildController::class, 'store'])->middleware('can:create-children');
            Route::get('{child}', [AdminChildController::class, 'show'])->middleware('can:view-children');
            Route::patch('{child}', [AdminChildController::class, 'update'])->middleware('can:update-children');
            Route::delete('{child}', [AdminChildController::class, 'destroy'])
                ->middleware(['can:delete-children', 'password.recent', 'throttle:sensitive']);
            Route::post('{child}/restore', [AdminChildController::class, 'restore'])->middleware('can:delete-children');
        });
        Route::get('announcements', [AdminDirectoryController::class, 'announcements']);
    });
    Route::get('teacher/dashboard', [TeacherController::class, 'dashboard']);
    Route::get('teacher/qr-workspace', [QrAttendanceController::class, 'workspace'])->middleware('can:create-attendance-qr');
    Route::get('teacher/attendance-workspace', [AttendanceController::class, 'workspace'])->middleware('can:view-attendance');
    Route::get('teachers/me/classes', [TeacherController::class, 'classes'])->middleware('can:view-classes');
    Route::get('teachers/me/children', [TeacherController::class, 'children'])->middleware('can:view-children');
    Route::apiResource('teacher/question-bank', TeacherQuestionBankController::class)
        ->parameters(['question-bank' => 'question'])
        ->except(['show']);
    Route::post('teacher/announcements/{announcement}/send', [TeacherAnnouncementController::class, 'send']);
    Route::post('teacher/announcements/{announcement}/withdraw', [TeacherAnnouncementController::class, 'withdraw']);
    Route::post('teacher/announcements/{announcement}/remind', [TeacherAnnouncementController::class, 'remind']);
    Route::apiResource('teacher/announcements', TeacherAnnouncementController::class);
    Route::post('teacher/assignments/{assignment}/publish', [TeacherAssignmentController::class, 'publish']);
    Route::patch('teacher/assignments/{assignment}/due-date', [TeacherAssignmentController::class, 'changeDueDate']);
    Route::put('teacher/assignments/{assignment}/accommodations/{child}', [TeacherAssignmentController::class, 'accommodate']);
    Route::post('teacher/assignments/{assignment}/close', [TeacherAssignmentController::class, 'close']);
    Route::post('teacher/assignments/{assignment}/withdraw', [TeacherAssignmentController::class, 'withdraw']);
    Route::get('teacher/assignments/{assignment}/report/export', [TeacherAssignmentReportController::class, 'export']);
    Route::get('teacher/assignments/{assignment}/report', [TeacherAssignmentReportController::class, 'show']);
    Route::get('teacher/assignments/{assignment}/submissions', [TeacherGradingController::class, 'index']);
    Route::post('teacher/assignments/{assignment}/release', [TeacherGradingController::class, 'release']);
    Route::patch('teacher/submissions/{submission}/grade', [TeacherGradingController::class, 'grade']);
    Route::post('teacher/submissions/{submission}/reopen', [TeacherGradingController::class, 'reopen']);
    Route::apiResource('teacher/assignments', TeacherAssignmentController::class);
    Route::get('child/assignments', [ChildAssignmentController::class, 'index']);
    Route::get('child/schedule', ChildScheduleController::class);
    Route::get('child/assignments/{assignment}', [ChildAssignmentController::class, 'show']);
    Route::post('child/assignments/{assignment}/attempts', [ChildAssignmentController::class, 'start']);
    Route::patch('child/submissions/{submission}/answers', [ChildAssignmentController::class, 'saveAnswers']);
    Route::post('child/submissions/{submission}/submit', [ChildAssignmentController::class, 'submit']);
    Route::post('teacher/assignments/{assignment}/files', [LearningFileController::class, 'storeAssignment'])->middleware('throttle:upload');
    Route::delete('teacher/assignment-files/{file}', [LearningFileController::class, 'destroyAssignment']);
    Route::post('child/submissions/{submission}/files', [LearningFileController::class, 'storeSubmission'])->middleware('throttle:upload');
    Route::delete('child/submission-files/{file}', [LearningFileController::class, 'destroySubmission']);
    Route::get('learning-files/assignments/{file}', [LearningFileController::class, 'downloadAssignment']);
    Route::get('learning-files/submissions/{file}', [LearningFileController::class, 'downloadSubmission']);
    Route::get('notifications', [NotificationInboxController::class, 'index']);
    Route::post('notifications/read-all', [NotificationInboxController::class, 'readAll']);
    Route::get('notifications/{announcement}', [NotificationInboxController::class, 'show']);
    Route::post('notifications/{announcement}/read', [NotificationInboxController::class, 'read']);
    Route::post('notifications/{announcement}/acknowledge', [NotificationInboxController::class, 'acknowledge']);
    Route::prefix('teacher/classes')->group(function () {
        Route::get('options', [TeacherClassController::class, 'options'])->middleware('can:view-classes');
        Route::get('{class}/workspace', [TeacherClassController::class, 'workspace'])->middleware('can:view-classes');
        Route::post('/', [TeacherClassController::class, 'store'])->middleware('can:create-classes');
        Route::get('{class}/enrollment-options', [TeacherClassController::class, 'enrollmentOptions'])->middleware('can:enroll-children');
        Route::post('{class}/enrollments', [TeacherClassController::class, 'storeEnrollment'])->middleware('can:enroll-children');
        Route::patch('{class}/enrollments/{child}', [TeacherClassController::class, 'updateEnrollment'])->middleware('can:enroll-children');
        Route::patch('{class}', [TeacherClassController::class, 'update'])->middleware('can:update-classes');
        Route::delete('{class}', [TeacherClassController::class, 'destroy'])->middleware('can:delete-classes');
    });
    Route::get('classes/{class}', [ClassController::class, 'show']);
    Route::get('classes/{class}/children', [ClassController::class, 'children']);
    Route::get('classes/{class}/attendance-sessions', [AttendanceController::class, 'index']);
    Route::post('classes/{class}/attendance-sessions', [AttendanceController::class, 'store']);
    Route::post('classes/{class}/attendance-qr', [QrAttendanceController::class, 'create']);
    Route::get('attendance-sessions/{session}', [AttendanceController::class, 'show']);
    Route::get('attendance-sessions/{session}/qr', [QrAttendanceController::class, 'sessionQr']);
    Route::post('attendance-sessions/{session}/qr', [QrAttendanceController::class, 'createForSession']);
    Route::post('attendance-sessions/{session}/end', [AttendanceController::class, 'end']);
    Route::post('attendance-sessions/{session}/cancel', [AttendanceController::class, 'cancel']);
    Route::delete('attendance-sessions/{session}', [AttendanceController::class, 'destroy']);
    Route::post('attendance-sessions/{session}/mark', [AttendanceController::class, 'mark']);
    Route::post('attendance-sessions/{session}/mark-all-present', [AttendanceController::class, 'markAll']);
    Route::get('attendance-sessions/{session}/summary', [AttendanceController::class, 'summary']);
    Route::get('parents/me/children', [QrAttendanceController::class, 'familyChildren']);
});

Route::post('attendance/qr/check-in', [QrAttendanceController::class, 'checkIn'])
    ->middleware(['web', 'child.device', 'throttle:qr-scan']);

if (app()->environment('testing')) {
    Route::get('security-test-error', fn () => throw new RuntimeException('database-secret-detail'));
}

Route::any('{path}', fn () => response()->json([
    'success' => false,
    'message' => 'Không tìm thấy tài nguyên.',
    'code' => 'NOT_FOUND',
], 404))->where('path', '.*');
