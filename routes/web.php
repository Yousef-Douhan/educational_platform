<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('cms/admin')->name('cms.admin.')->group(function () {
    Route::view('/', 'cms.dashboard')->name('dashboard');
    
    Route::get('users/trashed', [UserController::class, 'trashed'])->name('users.trashed');
    Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
    Route::delete('users/{id}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');

    // Standard Resource Routes
    Route::resource('users', UserController::class);

    Route::get('user-profiles/trashed', [UserProfileController::class, 'trashed'])
        ->name('user-profiles.trashed');
    Route::patch('user-profiles/{id}/restore', [UserProfileController::class, 'restore'])
        ->name('user-profiles.restore');
    Route::delete('user-profiles/{id}/force-delete', [UserProfileController::class, 'forceDelete'])
        ->name('user-profiles.force-delete');

    Route::resource('user-profiles', UserProfileController::class)
        ->parameters(['user-profiles' => 'user_profile']);
    
});
