<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Student\AttachmentController;
use App\Http\Controllers\Student\CartController;
use App\Http\Controllers\Student\CertificateController;
use App\Http\Controllers\Student\CatalogController;
use App\Http\Controllers\Student\CheckoutController;
use App\Http\Controllers\Student\EnrollController;
use App\Http\Controllers\Student\LearnController;
use App\Http\Controllers\Student\MyCoursesController;
use App\Http\Controllers\Student\LiveSessionController;
use App\Http\Controllers\Student\NotificationController;
use App\Http\Controllers\Student\OrderController;
use App\Http\Controllers\Student\PaymentController;
use App\Http\Controllers\Student\ProfileController;
use App\Http\Controllers\Student\QuizController;
use App\Http\Controllers\Student\ReceiptController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

/*
| واجهة الطالب. تُحمَّل من bootstrap/app.php داخل مجموعة web (جلسة + CSRF).
| الصفحات العامة لا تحتاج دخولًا، وكل ما يخص الحساب خلف auth ومقيَّد بصاحبه في الاستعلام.
*/
Route::get('/', [CatalogController::class, 'home'])->name('student.home');
Route::get('courses', [CatalogController::class, 'index'])->name('student.courses.index');
Route::get('courses/{slug}', [CatalogController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-_]{1,191}')->name('student.courses.show');

// الزائر يتصفح بحرية؛ زر الشراء/التسجيل يمرّ من هنا فيُطلب منه الدخول ثم يعود لصفحة الدورة
Route::get('courses/{slug}/continue', fn (string $slug) => redirect()->route('student.courses.show', $slug)
    ->with('status', __('student.course.logged_in_continue')))
    ->where('slug', '[A-Za-z0-9\-_]{1,191}')->middleware('auth')->name('student.courses.continue');

// الدرس: المعاينة المجانية متاحة للزائر، وغيرها يتحقق المتحكم من التسجيل فيه
Route::get('learn/{slug}/lessons/{lesson}', [LearnController::class, 'lesson'])
    ->where('slug', '[A-Za-z0-9\-_]{1,191}')->name('student.learn.lesson');

Route::post('locale/{locale}', [LocaleController::class, 'update'])
    ->where('locale', 'ar|en')->middleware('throttle:30,1')->name('locale.update');

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:10,60');

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'form'])
        ->where('token', '[A-Za-z0-9]{20,100}')->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->name('student.')->group(function () {
    Route::get('my', fn () => redirect()->route('student.courses.mine'))->name('dashboard');
    Route::get('my/courses', [MyCoursesController::class, 'index'])->name('courses.mine');

    Route::post('courses/{slug}/enroll', [EnrollController::class, 'store'])
        ->where('slug', '[A-Za-z0-9\-_]{1,191}')->middleware('throttle:20,1')->name('courses.enroll');

    Route::get('learn/{slug}', [LearnController::class, 'course'])
        ->where('slug', '[A-Za-z0-9\-_]{1,191}')->name('learn.course');
    Route::post('learn/{slug}/lessons/{lesson}/complete', [LearnController::class, 'complete'])
        ->where('slug', '[A-Za-z0-9\-_]{1,191}')->middleware('throttle:60,1')->name('learn.complete');
    Route::delete('learn/{slug}/lessons/{lesson}/complete', [LearnController::class, 'uncomplete'])
        ->where('slug', '[A-Za-z0-9\-_]{1,191}')->middleware('throttle:60,1')->name('learn.uncomplete');

    Route::post('quizzes/{quiz}/start', [QuizController::class, 'start'])->middleware('throttle:20,1')->name('quizzes.start');
    Route::get('my/attempts/{attempt}', [QuizController::class, 'show'])->name('attempts.show');
    Route::post('my/attempts/{attempt}', [QuizController::class, 'submit'])->middleware('throttle:30,1')->name('attempts.submit');

    Route::get('my/attachments/{attachment}', [AttachmentController::class, 'download'])->name('attachments.download');

    Route::get('my/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('my/profile', [ProfileController::class, 'update'])->middleware('throttle:20,1')->name('profile.update');
    Route::put('my/password', [ProfileController::class, 'password'])->middleware('throttle:6,1')->name('password.update');

    Route::get('my/live', [LiveSessionController::class, 'index'])->name('live.index');
    Route::get('my/live/{session}/join', [LiveSessionController::class, 'join'])
        ->whereNumber('session')->middleware('throttle:30,1')->name('live.join');

    Route::get('my/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('my/notifications/read-all', [NotificationController::class, 'readAll'])->middleware('throttle:30,1')->name('notifications.read-all');
    Route::post('my/notifications/{notification}/read', [NotificationController::class, 'read'])->middleware('throttle:60,1')->name('notifications.read');
    Route::delete('my/notifications/{notification}', [NotificationController::class, 'destroy'])->middleware('throttle:60,1')->name('notifications.destroy');

    Route::get('my/certificates', [CertificateController::class, 'index'])->name('certificates.index');
    Route::post('my/certificates/claim/{slug}', [CertificateController::class, 'claim'])
        ->where('slug', '[A-Za-z0-9\-_]{1,191}')->middleware('throttle:10,1')->name('certificates.claim');
    Route::get('my/certificates/{certificate}/download', [CertificateController::class, 'download'])
        ->middleware('throttle:30,1')->name('certificates.download');

    Route::get('cart', [CartController::class, 'show'])->name('cart.show');
    Route::post('cart/items', [CartController::class, 'add'])->middleware('throttle:60,1')->name('cart.add');
    Route::delete('cart/items/{course}', [CartController::class, 'remove'])->name('cart.remove');

    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout');

    Route::get('my/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('my/orders/{number}', [OrderController::class, 'show'])
        ->where('number', 'ORD-[A-Z0-9]{8}')->name('orders.show');
    Route::post('my/orders/{number}/receipt', [ReceiptController::class, 'store'])
        ->where('number', 'ORD-[A-Z0-9]{8}')->middleware('throttle:6,1')->name('orders.receipt');
    Route::get('my/receipts/{receipt}', [ReceiptController::class, 'image'])->name('receipts.image');
    Route::post('my/orders/{number}/pay', [PaymentController::class, 'store'])
        ->where('number', 'ORD-[A-Z0-9]{8}')->middleware('throttle:6,1')->name('orders.pay');
});
