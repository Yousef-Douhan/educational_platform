<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourseController;


Route::get('/', function () {
    return view('welcome');
});

/* ---------------------------- Auth ---------------------------- */
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});
Route::post('logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
|--------------------------------------------------------------------------
| CMS (محمي بالدخول + الصلاحيات)
|--------------------------------------------------------------------------
| كل مسار يتطلب صلاحية بصيغة resource.action. دور super-admin يتجاوز الكل
| عبر Gate::before في AppServiceProvider.
|
| ملاحظة: مسارات trashed/restore/force-delete يجب أن تسبق الـ resource
| حتى لا يلتقطها المسار users/{user}.
*/
Route::prefix('cms/admin')->name('cms.admin.')->middleware('auth')->group(function () {
    Route::view('/', 'cms.dashboard')->name('dashboard');

    /* ---------------------------- Users ---------------------------- */
    Route::get('users/trashed', [UserController::class, 'trashed'])
        ->middleware('can:users.restore')->name('users.trashed');
    Route::post('users/{id}/restore', [UserController::class, 'restore'])
        ->middleware('can:users.restore')->name('users.restore');
    Route::delete('users/{id}/force-delete', [UserController::class, 'forceDelete'])
        ->middleware('can:users.force-delete')->name('users.force-delete');

    Route::resource('users', UserController::class)->only(['index', 'show'])->middleware('can:users.view');
    Route::resource('users', UserController::class)->only(['store'])->middleware('can:users.create');
    Route::resource('users', UserController::class)->only(['edit', 'update'])->middleware('can:users.edit');
    Route::resource('users', UserController::class)->only(['destroy'])->middleware('can:users.delete');

    /* ------------------------- User profiles ------------------------ */
    Route::get('user-profiles/trashed', [UserProfileController::class, 'trashed'])
        ->middleware('can:user-profiles.restore')->name('user-profiles.trashed');
    Route::patch('user-profiles/{id}/restore', [UserProfileController::class, 'restore'])
        ->middleware('can:user-profiles.restore')->name('user-profiles.restore');
    Route::delete('user-profiles/{id}/force-delete', [UserProfileController::class, 'forceDelete'])
        ->middleware('can:user-profiles.force-delete')->name('user-profiles.force-delete');

    $profileParams = ['user-profiles' => 'user_profile'];
    Route::resource('user-profiles', UserProfileController::class)->only(['index', 'show'])
        ->parameters($profileParams)->middleware('can:user-profiles.view');
    Route::resource('user-profiles', UserProfileController::class)->only(['create', 'store'])
        ->parameters($profileParams)->middleware('can:user-profiles.create');
    Route::resource('user-profiles', UserProfileController::class)->only(['edit', 'update'])
        ->parameters($profileParams)->middleware('can:user-profiles.edit');
    Route::resource('user-profiles', UserProfileController::class)->only(['destroy'])
        ->parameters($profileParams)->middleware('can:user-profiles.delete');

    /* ------------------------------ Roles --------------------------- */
    Route::resource('roles', RoleController::class)->only(['index', 'show'])->middleware('can:roles.view');
    Route::resource('roles', RoleController::class)->only(['create', 'store'])->middleware('can:roles.create');
    Route::resource('roles', RoleController::class)->only(['edit', 'update'])->middleware('can:roles.edit');
    Route::resource('roles', RoleController::class)->only(['destroy'])->middleware('can:roles.delete');

    /* --------------------------- Permissions ------------------------ */
    Route::resource('permissions', PermissionController::class)->only(['index', 'show'])->middleware('can:permissions.view');
    Route::resource('permissions', PermissionController::class)->only(['create', 'store'])->middleware('can:permissions.create');
    Route::resource('permissions', PermissionController::class)->only(['edit', 'update'])->middleware('can:permissions.edit');
    Route::resource('permissions', PermissionController::class)->only(['destroy'])->middleware('can:permissions.delete');


    Route::get('categories/trashed', [CategoryController::class, 'trashed'])->name('categories.trashed');
    Route::patch('categories/{id}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
    Route::delete('categories/{id}/force-delete', [CategoryController::class, 'forceDelete'])->name('categories.force-delete');

    Route::resource('categories', CategoryController::class);


    Route::get('courses/trashed', [CourseController::class, 'trashed'])->name('courses.trashed');
    Route::patch('courses/{id}/restore', [CourseController::class, 'restore'])->name('courses.restore');
    Route::delete('courses/{id}/force-delete', [CourseController::class, 'forceDelete'])->name('courses.force-delete');

    Route::resource('courses', CourseController::class);
});
