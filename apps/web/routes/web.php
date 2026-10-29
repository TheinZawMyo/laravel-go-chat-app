<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('chats/{conversation?}', ChatController::class)->name('chats.index');
});

require __DIR__.'/settings.php';
