<?php

use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\CourseProgressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LessonAttachmentController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\MyNotificationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\SectionController;


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
    // لوحة التحكم للموظفين فقط؛ الطالب يُحوَّل لواجهته
    Route::get('/', \App\Http\Controllers\DashboardController::class)->middleware('staff')->name('dashboard');

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

    /* ------------------------ Quiz attempts ------------------------- */
    Route::get('quiz-attempts', [QuizAttemptController::class, 'index'])->name('quiz-attempts.index');
    Route::get('quiz-attempts/{attempt}', [QuizAttemptController::class, 'show'])->name('quiz-attempts.show');
    Route::post('quiz-attempts/{attempt}/void', [QuizAttemptController::class, 'void'])->name('quiz-attempts.void');

    /* ------------------------ Student progress ---------------------- */
    Route::get('progress', [CourseProgressController::class, 'index'])->name('progress.index');
    Route::get('progress/{progress}', [CourseProgressController::class, 'show'])->name('progress.show');
    Route::post('progress/{progress}/recalculate', [CourseProgressController::class, 'recalculate'])->name('progress.recalculate');

    /* ------------------------ Live sessions ------------------------- */
    Route::get('live-sessions/trashed', [LiveSessionController::class, 'trashed'])->name('live-sessions.trashed');
    Route::patch('live-sessions/{id}/restore', [LiveSessionController::class, 'restore'])->name('live-sessions.restore');
    Route::delete('live-sessions/{id}/force-delete', [LiveSessionController::class, 'forceDelete'])->name('live-sessions.force-delete');
    Route::post('live-sessions/{live_session}/cancel', [LiveSessionController::class, 'cancel'])->name('live-sessions.cancel');
    Route::resource('live-sessions', LiveSessionController::class);

    /* ------------------------ Audit log (read only) ----------------- */
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('audit-logs/{audit_log}', [AuditLogController::class, 'show'])->name('audit-logs.show');

    /* ------------------------ Notifications ------------------------- */
    // إشعاراتي: لكل مستخدم مسجّل، ولا تحتاج صلاحية لأنها مقيّدة بصاحبها
    Route::get('my-notifications', [MyNotificationController::class, 'index'])->name('my-notifications.index');
    Route::get('my-notifications/latest', [MyNotificationController::class, 'latest'])->name('my-notifications.latest');
    Route::post('my-notifications/read-all', [MyNotificationController::class, 'readAll'])->name('my-notifications.read-all');
    Route::post('my-notifications/{notification}/read', [MyNotificationController::class, 'read'])->name('my-notifications.read');
    Route::delete('my-notifications/{notification}', [MyNotificationController::class, 'destroy'])->name('my-notifications.destroy');
    // كل إشعارات المنصة: قراءة فقط للإدارة
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('notifications.show');

    Route::get('quizzes/{quiz}/questions', [QuestionController::class, 'index'])->name('quizzes.questions.index');
    Route::post('quizzes/{quiz}/questions', [QuestionController::class, 'store'])->name('quizzes.questions.store');
    Route::post('quizzes/{quiz}/questions/reorder', [QuestionController::class, 'reorder'])->name('quizzes.questions.reorder');
    Route::get('quizzes/{quiz}/questions/trashed', [QuestionController::class, 'trashed'])->name('quizzes.questions.trashed');
    Route::put('questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
    Route::patch('questions/{id}/restore', [QuestionController::class, 'restore'])->name('questions.restore');
    Route::delete('questions/{id}/force-delete', [QuestionController::class, 'forceDelete'])->name('questions.force-delete');




    Route::get('certificate-templates/trashed', [CertificateTemplateController::class, 'trashed'])->name('certificate-templates.trashed');
    Route::patch('certificate-templates/{id}/restore', [CertificateTemplateController::class, 'restore'])->name('certificate-templates.restore');
    Route::delete('certificate-templates/{id}/force-delete', [CertificateTemplateController::class, 'forceDelete'])->name('certificate-templates.force-delete');
    Route::resource('certificate-templates', CertificateTemplateController::class);

    Route::get('certificates/eligible', [CertificateController::class, 'eligible'])->name('certificates.eligible');
    Route::post('certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])->name('certificates.revoke');
    Route::post('certificates/{certificate}/reinstate', [CertificateController::class, 'reinstate'])->name('certificates.reinstate');
    Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download');
    Route::resource('certificates', CertificateController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('certificate-templates/{certificate_template}/preview', [CertificateTemplateController::class, 'preview'])->name('certificate-templates.preview');

    /* ---------------------------- Coupons --------------------------- */
    Route::get('coupons/trashed', [CouponController::class, 'trashed'])->name('coupons.trashed');
    Route::patch('coupons/{id}/restore', [CouponController::class, 'restore'])->name('coupons.restore');
    Route::delete('coupons/{id}/force-delete', [CouponController::class, 'forceDelete'])->name('coupons.force-delete');
    Route::patch('coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->name('coupons.toggle');
    Route::resource('coupons', CouponController::class);

    /* ----------------------------- Carts ---------------------------- */
    Route::post('carts/{cart}/clear', [CartController::class, 'clear'])->name('carts.clear');
    Route::resource('carts', CartController::class)->only(['index', 'show']);

    /* ----------------------------- Orders --------------------------- */
    Route::post('orders/{order}/mark-paid', [OrderController::class, 'markPaid'])->name('orders.mark-paid');
    Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->name('orders.refund');
    Route::resource('orders', OrderController::class)->only(['index', 'show']);

    /* ---------------------------- Payments -------------------------- */
    Route::resource('payments', PaymentController::class)->only(['index', 'show']);

    /* ------------------- إشعارات التحويل (مراجعة) ------------------- */
    Route::get('receipts', [PaymentReceiptController::class, 'index'])->name('receipts.index');
    Route::get('receipts/{receipt}', [PaymentReceiptController::class, 'show'])->name('receipts.show');
    Route::get('receipts/{receipt}/image', [PaymentReceiptController::class, 'image'])->name('receipts.image');
    Route::post('receipts/{receipt}/approve', [PaymentReceiptController::class, 'approve'])->name('receipts.approve');
    Route::post('receipts/{receipt}/reject', [PaymentReceiptController::class, 'reject'])->name('receipts.reject');
    
});




use App\Http\Controllers\CertificateVerificationController;

Route::middleware('throttle:certificate-verify')->group(function () {
    Route::get('certificates/verify', [CertificateVerificationController::class, 'form'])->name('certificates.verify.form');
    Route::get('certificates/verify/{code}', [CertificateVerificationController::class, 'show'])
        ->where('code', '[A-Za-z0-9\-]{1,100}')->name('certificates.verify');
});
