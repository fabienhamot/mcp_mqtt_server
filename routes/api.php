<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DevicePermissionController;
use App\Http\Controllers\Api\DisplayLogController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::middleware('auth:api')->group(function () {
    Route::apiResource('devices', DeviceController::class);
    Route::apiResource('users', UserController::class)->only(['index', 'store', 'show', 'update']);
    Route::get('display-logs', [DisplayLogController::class, 'index']);

    Route::get('devices/{device}/permissions', [DevicePermissionController::class, 'index']);
    Route::post('devices/{device}/permissions', [DevicePermissionController::class, 'store']);
    Route::delete('devices/{device}/permissions/{user}', [DevicePermissionController::class, 'destroy']);
});
