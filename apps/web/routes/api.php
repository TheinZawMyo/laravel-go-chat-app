<?php

use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->prefix('v1')->group(function () {
    Route::get('me', [MeController::class, 'show'])->name('api.v1.me');

    Route::get('users/{username}', [UserController::class, 'show'])->name('api.v1.users.show');

    Route::get('devices', [DeviceController::class, 'index'])->name('api.v1.devices.index');
    Route::delete('devices/{device}', [DeviceController::class, 'destroy'])->name('api.v1.devices.destroy');
});
