<?php

use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Admin\AuthenticatedSessionController as AdminAuthenticatedSessionController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StampRequestController as AdminStampRequestController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\StampRequestController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
// 一般ユーザー
Route::middleware('auth')->group(function () {
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
    Route::get('/attendance/list', [AttendanceController::class, 'list'])->name('attendance.list');
    // 勤怠詳細・修正は一般ユーザーと管理者で共通のパス（提供Bladeに合わせる。コントローラー内で role を見て処理を切り替える）
    Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->name('attendance.show');
    Route::post('/attendance/{id}', [AttendanceController::class, 'update'])->name('attendance.update');
    Route::get('/stamp_correction_request/list', [StampRequestController::class, 'index'])
        ->name('stamp_correction_request.list');
});

// 管理者
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AdminAuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('/login', [AdminAuthenticatedSessionController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/attendance/list', [AdminAttendanceController::class, 'index'])->name('attendance.list');
        Route::get('/attendance/staff/{id}', [StaffController::class, 'staff'])->name('attendance.staff');
        Route::get('/staff/list', [StaffController::class, 'index'])->name('staff.list');
        Route::post('/logout', [AdminAuthenticatedSessionController::class, 'destroy'])->name('logout');

    });
});

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminStampRequestController::class, 'show'])
        ->name('stamp_correction_request.approve');
    Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [AdminStampRequestController::class, 'approve'])
        ->name('stamp_correction_request.approve.store');
});
