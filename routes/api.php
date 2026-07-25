<?php
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\{ClassController,TeacherController};
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdminDirectoryController;

Route::prefix('auth')->middleware('web')->group(function(){
 Route::post('login',[AuthController::class,'login'])->middleware('throttle:login');
 Route::post('forgot-password',[AuthController::class,'forgot'])->middleware('throttle:6,1');
 Route::post('reset-password',[AuthController::class,'reset'])->middleware('throttle:6,1');
 Route::middleware('auth:sanctum')->group(function(){
  Route::post('logout',[AuthController::class,'logout']); Route::get('me',[AuthController::class,'me']);
  Route::post('change-password',[AuthController::class,'change']); Route::get('sessions',[AuthController::class,'sessions']);
  Route::delete('sessions/others',[AuthController::class,'destroyOtherSessions']); Route::delete('sessions/{session}',[AuthController::class,'destroySession']);
 });
});
Route::middleware('auth:sanctum')->group(function(){
 Route::get('admin/dashboard',AdminDashboardController::class);
 Route::prefix('admin')->group(function(){
  Route::get('parishes',[AdminDirectoryController::class,'parishes']);
  Route::get('teachers',[AdminDirectoryController::class,'teachers']);
  Route::get('parents',[AdminDirectoryController::class,'parents']);
  Route::get('children',[AdminDirectoryController::class,'children']);
  Route::get('classes',[AdminDirectoryController::class,'classes']);
  Route::get('announcements',[AdminDirectoryController::class,'announcements']);
 });
 Route::get('teacher/dashboard',[TeacherController::class,'dashboard']);
 Route::get('teachers/me/classes',[TeacherController::class,'classes']);
 Route::get('classes/{class}',[ClassController::class,'show']);
 Route::get('classes/{class}/children',[ClassController::class,'children']);
 Route::get('classes/{class}/attendance-sessions',[AttendanceController::class,'index']);
 Route::post('classes/{class}/attendance-sessions',[AttendanceController::class,'store']);
 Route::get('attendance-sessions/{session}',[AttendanceController::class,'show']);
 Route::post('attendance-sessions/{session}/mark',[AttendanceController::class,'mark']);
 Route::post('attendance-sessions/{session}/mark-all-present',[AttendanceController::class,'markAll']);
 Route::get('attendance-sessions/{session}/summary',[AttendanceController::class,'summary']);
});
