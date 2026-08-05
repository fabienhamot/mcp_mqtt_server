<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DevicePermissionController;
use App\Http\Controllers\Api\DisplayLogController;
use App\Http\Controllers\Api\MeDeviceCommandController;
use App\Http\Controllers\Api\MeDeviceController;
use App\Http\Controllers\Api\MeDisplayLogController;
use App\Http\Controllers\Api\MePasswordController;
use App\Http\Controllers\Api\MeProfileController;
use App\Http\Controllers\Api\MeTokenController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [AuthTokenController::class, 'store'])
    ->middleware('throttle:10,1');

Route::middleware('auth:api')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/me', fn (Request $request) => $request->user());
    Route::patch('/me', [MeProfileController::class, 'update']);
    Route::put('/me/password', [MePasswordController::class, 'update']);
    Route::get('/me/tokens', [MeTokenController::class, 'index']);
    Route::post('/me/tokens', [MeTokenController::class, 'store']);
    Route::delete('/me/tokens/{tokenId}', [MeTokenController::class, 'destroy']);
    Route::post('/auth/logout', [AuthTokenController::class, 'destroy']);

    Route::get('/me/devices', [MeDeviceController::class, 'index']);
    Route::get('/me/devices/{device}', [MeDeviceController::class, 'show']);
    Route::post('/me/devices/{device}/commands', [MeDeviceCommandController::class, 'store']);
    Route::get('/me/display-logs', [MeDisplayLogController::class, 'index']);

    Route::apiResource('devices', DeviceController::class);
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'show', 'update']);
    Route::get('display-logs', [DisplayLogController::class, 'index']);

    Route::get('devices/{device}/permissions', [DevicePermissionController::class, 'index']);
    Route::post('devices/{device}/permissions', [DevicePermissionController::class, 'store']);
    Route::delete('devices/{device}/permissions/{user}', [DevicePermissionController::class, 'destroy']);
});
