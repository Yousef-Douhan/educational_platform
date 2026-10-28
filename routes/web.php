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
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LessonAttachmentController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SectionController;

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
    Route::resource('roles', RoleController::class);


    /* --------------------------- Permissions ------------------------ */
    Route::resource('permissions', PermissionController::class);



    Route::get('categories/trashed', [CategoryController::class, 'trashed'])->name('categories.trashed');
    Route::patch('categories/{id}/restore', [CategoryController::class, 'restore'])->name('categories.restore');
    Route::delete('categories/{id}/force-delete', [CategoryController::class, 'forceDelete'])->name('categories.force-delete');

    Route::resource('categories', CategoryController::class);


    Route::get('courses/trashed', [CourseController::class, 'trashed'])->name('courses.trashed');
    Route::patch('courses/{id}/restore', [CourseController::class, 'restore'])->name('courses.restore');
    Route::delete('courses/{id}/force-delete', [CourseController::class, 'forceDelete'])->name('courses.force-delete');

    Route::resource('courses', CourseController::class);


    // المنهج والأقسام
    Route::get('courses/{course}/curriculum', [SectionController::class, 'index'])->name('courses.curriculum');
    Route::post('courses/{course}/sections', [SectionController::class, 'store'])->name('courses.sections.store');
    Route::post('courses/{course}/sections/reorder', [SectionController::class, 'reorder'])->name('courses.sections.reorder');
    Route::put('sections/{section}', [SectionController::class, 'update'])->name('sections.update');
    Route::delete('sections/{section}', [SectionController::class, 'destroy'])->name('sections.destroy');

    // الدروس
    Route::get('courses/{course}/lessons/trashed', [LessonController::class, 'trashed'])->name('courses.lessons.trashed');
    Route::post('sections/{section}/lessons', [LessonController::class, 'store'])->name('sections.lessons.store');
    Route::post('sections/{section}/lessons/reorder', [LessonController::class, 'reorder'])->name('sections.lessons.reorder');
    Route::patch('lessons/{id}/restore', [LessonController::class, 'restore'])->name('lessons.restore');
    Route::delete('lessons/{id}/force-delete', [LessonController::class, 'forceDelete'])->name('lessons.force-delete');
    Route::get('lessons/{lesson}', [LessonController::class, 'show'])->name('lessons.show');
    Route::put('lessons/{lesson}', [LessonController::class, 'update'])->name('lessons.update');
    Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->name('lessons.destroy');

    // المرفقات
    Route::post('lessons/{lesson}/attachments', [LessonAttachmentController::class, 'store'])->name('lessons.attachments.store');
    Route::get('attachments/{attachment}/download', [LessonAttachmentController::class, 'download'])->name('attachments.download');
    Route::delete('attachments/{attachment}', [LessonAttachmentController::class, 'destroy'])->name('attachments.destroy');


    Route::get('enrollments/lookup/users', [EnrollmentController::class, 'lookupUsers'])->name('enrollments.lookup-users');
    Route::resource('enrollments', EnrollmentController::class)->except(['destroy']);


    Route::get('quizzes/trashed', [QuizController::class, 'trashed'])->name('quizzes.trashed');
    Route::patch('quizzes/{id}/restore', [QuizController::class, 'restore'])->name('quizzes.restore');
    Route::delete('quizzes/{id}/force-delete', [QuizController::class, 'forceDelete'])->name('quizzes.force-delete');
    Route::resource('quizzes', QuizController::class);

    Route::get('quizzes/{quiz}/questions', [QuestionController::class, 'index'])->name('quizzes.questions.index');
    Route::post('quizzes/{quiz}/questions', [QuestionController::class, 'store'])->name('quizzes.questions.store');
    Route::post('quizzes/{quiz}/questions/reorder', [QuestionController::class, 'reorder'])->name('quizzes.questions.reorder');
    Route::get('quizzes/{quiz}/questions/trashed', [QuestionController::class, 'trashed'])->name('quizzes.questions.trashed');
    Route::put('questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
    Route::patch('questions/{id}/restore', [QuestionController::class, 'restore'])->name('questions.restore');
    Route::delete('questions/{id}/force-delete', [QuestionController::class, 'forceDelete'])->name('questions.force-delete');
});
