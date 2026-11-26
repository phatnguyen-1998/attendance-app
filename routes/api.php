<?php

use App\Http\Controllers\Api\V1\AttendanceRecordController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    // 読み取り系：認証不要
    Route::apiResource('attendance-records', AttendanceRecordController::class)
        ->only(['index', 'show'])
        ->parameters(['attendance-records' => 'attendanceRecord']);

    // 書き込み系：Sanctum 認証必須
    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('attendance-records', AttendanceRecordController::class)
            ->only(['store', 'update', 'destroy'])
            ->parameters(['attendance-records' => 'attendanceRecord']);
    });
});
