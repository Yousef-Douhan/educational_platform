<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('cms/admin')->group(function () {
    Route::view('/', 'cms.dashboard')->name('dashboard');

});
