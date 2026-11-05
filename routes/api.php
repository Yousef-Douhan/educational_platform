<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\PasswordController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Student as S;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1  (البادئة api/v1 مضبوطة في bootstrap/app.php)
|--------------------------------------------------------------------------
| المصادقة: Bearer token من Passport (الحارس api). اللغة: ترويسة Accept-Language (ar|en).
*/

// ---------- عام (بدون تسجيل دخول) ----------
Route::middleware('throttle:api')->group(function () {
    Route::get('categories', [CatalogController::class, 'categories'])->name('api.categories');
    Route::get('courses', [CatalogController::class, 'courses'])->name('api.courses.index');
    Route::get('courses/{slug}', [CatalogController::class, 'course'])->name('api.courses.show');
});

// ---------- المصادقة ----------
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:api-auth')->name('api.auth.register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:api-auth')->name('api.auth.login');
    Route::post('forgot-password', [PasswordController::class, 'forgot'])->middleware('throttle:api-auth')->name('api.auth.forgot');
    Route::post('reset-password', [PasswordController::class, 'reset'])->middleware('throttle:api-auth')->name('api.auth.reset');
    Route::post('email/verification-notification', [PasswordController::class, 'resendVerification'])->middleware(['auth:api', 'active', 'throttle:3,1'])->name('api.auth.verify-resend');
    Route::post('logout', [AuthController::class, 'logout'])->middleware(['auth:api', 'active'])->name('api.auth.logout');
    Route::post('logout-all', [AuthController::class, 'logoutAll'])->middleware(['auth:api', 'active'])->name('api.auth.logout-all');
});

// ---------- المستخدم الحالي ----------
Route::middleware(['auth:api', 'active', 'throttle:api'])->group(function () {
    Route::get('me', [ProfileController::class, 'show'])->name('api.me');
    Route::put('me', [ProfileController::class, 'update'])->name('api.me.update');
    Route::put('me/password', [ProfileController::class, 'password'])->name('api.me.password');
});

// ---------- واجهة الطالب (رمز بصلاحية student) ----------
Route::middleware(['auth:api', 'active', 'token.scope:student', 'throttle:api'])->group(function () {
    // دوراتي والتعلم
    Route::get('my-courses', [S\MyCoursesController::class, 'index'])->name('api.my-courses');
    Route::post('courses/{slug}/enroll', [S\MyCoursesController::class, 'enroll'])->middleware('throttle:20,1')->name('api.courses.enroll');
    Route::get('learn/{slug}', [S\LearnController::class, 'curriculum'])->name('api.learn.curriculum');
    Route::get('learn/{slug}/lessons/{lesson}', [S\LearnController::class, 'lesson'])->whereNumber('lesson')->name('api.learn.lesson');
    Route::post('learn/{slug}/lessons/{lesson}/complete', [S\LearnController::class, 'complete'])->whereNumber('lesson')->name('api.learn.complete');
    Route::delete('learn/{slug}/lessons/{lesson}/complete', [S\LearnController::class, 'uncomplete'])->whereNumber('lesson')->name('api.learn.uncomplete');
    Route::get('attachments/{attachment}/download', [S\LearnController::class, 'attachment'])->whereNumber('attachment')->name('api.attachments.download');

    // السلة والطلبات والدفع
    Route::get('cart', [S\CartController::class, 'show'])->name('api.cart');
    Route::post('cart', [S\CartController::class, 'add'])->middleware('throttle:30,1')->name('api.cart.add');
    Route::delete('cart/{course}', [S\CartController::class, 'remove'])->whereNumber('course')->name('api.cart.remove');
    Route::post('checkout', [S\CartController::class, 'checkout'])->middleware('throttle:10,1')->name('api.checkout');
    Route::get('orders', [S\OrderController::class, 'index'])->name('api.orders.index');
    Route::get('orders/{number}', [S\OrderController::class, 'show'])->name('api.orders.show');
    Route::post('orders/{number}/pay', [S\OrderController::class, 'pay'])->middleware('throttle:10,1')->name('api.orders.pay');
    Route::post('orders/{number}/receipts', [S\OrderController::class, 'submitReceipt'])->middleware('throttle:10,1')->name('api.orders.receipts');
    Route::get('payment-methods', [S\OrderController::class, 'methodsList'])->name('api.payment-methods');
    Route::get('receipts/{receipt}/image', [S\OrderController::class, 'receiptImage'])->whereNumber('receipt')->name('api.receipts.image');

    // الاختبارات
    Route::post('quizzes/{quiz}/attempts', [S\QuizController::class, 'start'])->whereNumber('quiz')->middleware('throttle:20,1')->name('api.quizzes.start');
    Route::get('attempts/{attempt}', [S\QuizController::class, 'show'])->whereNumber('attempt')->name('api.attempts.show');
    Route::post('attempts/{attempt}/submit', [S\QuizController::class, 'submit'])->whereNumber('attempt')->name('api.attempts.submit');

    // الشهادات
    Route::get('certificates', [S\CertificateController::class, 'index'])->name('api.certificates');
    Route::post('courses/{slug}/certificate', [S\CertificateController::class, 'claim'])->middleware('throttle:10,1')->name('api.certificates.claim');
    Route::get('certificates/{certificate}/download', [S\CertificateController::class, 'download'])->whereNumber('certificate')->name('api.certificates.download');

    // الإشعارات والجلسات المباشرة
    Route::get('notifications', [S\NotificationController::class, 'index'])->name('api.notifications');
    Route::post('notifications/read-all', [S\NotificationController::class, 'readAll'])->name('api.notifications.read-all');
    Route::post('notifications/{notification}/read', [S\NotificationController::class, 'read'])->name('api.notifications.read');
    Route::delete('notifications/{notification}', [S\NotificationController::class, 'destroy'])->name('api.notifications.destroy');
    Route::get('live-sessions', [S\LiveSessionController::class, 'index'])->name('api.live');
    Route::post('live-sessions/{session}/join', [S\LiveSessionController::class, 'join'])->whereNumber('session')->name('api.live.join');
});

// ---------- API الإدارة (رمز بصلاحية admin + موظف) ----------
Route::prefix('admin')->middleware(['auth:api', 'active', 'token.scope:admin', 'staff', 'throttle:api-admin'])
    ->group(base_path('routes/api_admin.php'));
