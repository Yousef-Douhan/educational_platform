<?php

use App\Http\Controllers\Api\V1\Admin as A;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\LessonAttachmentController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateTemplateController;
use App\Http\Controllers\CourseProgressController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LiveSessionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\QuizAttemptController;
use App\Http\Controllers\QuizController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API الإدارة  (/api/v1/admin)  — رمز بصلاحية admin + موظف
|--------------------------------------------------------------------------
| القوائم (ترقيم صفحات JSON) في Api\V1\Admin\*ListController. أما show/store/update/destroy/
| restore/force-delete فتُوجَّه إلى نفس متحكمات الـCMS (ردودها JSON أصلًا)، فتبقى الصلاحيات
| والسياسات وقواعد التحقق وحماية الحذف واحدة في المكانين. الصلاحيات (permission:*) معرّفة
| داخل تلك المتحكمات، وهنا للقوائم الجديدة فقط.
| ملاحظة: الرفع مع التعديل يكون POST + حقل _method=PUT (قيد PHP مع multipart).
*/

/* ---------------------------- التصنيفات ---------------------------- */
Route::get('categories', [A\CategoryListController::class, 'index'])->middleware('permission:categories.view')->name('api.admin.categories.index');
Route::get('categories/trashed', [A\CategoryListController::class, 'trashed'])->middleware('permission:categories.view')->name('api.admin.categories.trashed');
Route::patch('categories/{id}/restore', [CategoryController::class, 'restore'])->whereNumber('id')->name('api.admin.categories.restore');
Route::delete('categories/{id}/force-delete', [CategoryController::class, 'forceDelete'])->whereNumber('id')->name('api.admin.categories.force-delete');
Route::post('categories', [CategoryController::class, 'store'])->name('api.admin.categories.store');
Route::get('categories/{category}', [CategoryController::class, 'show'])->whereNumber('category')->name('api.admin.categories.show');
Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])->whereNumber('category')->name('api.admin.categories.update');
Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->whereNumber('category')->name('api.admin.categories.destroy');

/* ------------------------------ الدورات ----------------------------- */
Route::get('courses', [A\CourseListController::class, 'index'])->name('api.admin.courses.index');
Route::get('courses/trashed', [A\CourseListController::class, 'trashed'])->name('api.admin.courses.trashed');
Route::patch('courses/{id}/restore', [CourseController::class, 'restore'])->whereNumber('id')->name('api.admin.courses.restore');
Route::delete('courses/{id}/force-delete', [CourseController::class, 'forceDelete'])->whereNumber('id')->name('api.admin.courses.force-delete');
Route::post('courses', [CourseController::class, 'store'])->name('api.admin.courses.store');
Route::get('courses/{course}', [CourseController::class, 'show'])->whereNumber('course')->name('api.admin.courses.show');
Route::get('courses/{course}/edit', [CourseController::class, 'edit'])->whereNumber('course')->name('api.admin.courses.edit');
Route::match(['put', 'patch'], 'courses/{course}', [CourseController::class, 'update'])->whereNumber('course')->name('api.admin.courses.update');
Route::delete('courses/{course}', [CourseController::class, 'destroy'])->whereNumber('course')->name('api.admin.courses.destroy');

/* ---------------------- المنهج: أقسام، دروس، مرفقات ---------------------- */
Route::get('courses/{course}/curriculum', [SectionController::class, 'index'])->whereNumber('course')->name('api.admin.curriculum');
Route::post('courses/{course}/sections', [SectionController::class, 'store'])->whereNumber('course')->name('api.admin.sections.store');
Route::post('courses/{course}/sections/reorder', [SectionController::class, 'reorder'])->whereNumber('course')->name('api.admin.sections.reorder');
Route::put('sections/{section}', [SectionController::class, 'update'])->whereNumber('section')->name('api.admin.sections.update');
Route::delete('sections/{section}', [SectionController::class, 'destroy'])->whereNumber('section')->name('api.admin.sections.destroy');

Route::get('courses/{course}/lessons/trashed', [LessonController::class, 'trashed'])->whereNumber('course')->name('api.admin.lessons.trashed');
Route::post('sections/{section}/lessons', [LessonController::class, 'store'])->whereNumber('section')->name('api.admin.lessons.store');
Route::post('sections/{section}/lessons/reorder', [LessonController::class, 'reorder'])->whereNumber('section')->name('api.admin.lessons.reorder');
Route::patch('lessons/{id}/restore', [LessonController::class, 'restore'])->whereNumber('id')->name('api.admin.lessons.restore');
Route::delete('lessons/{id}/force-delete', [LessonController::class, 'forceDelete'])->whereNumber('id')->name('api.admin.lessons.force-delete');
Route::get('lessons/{lesson}', [LessonController::class, 'show'])->whereNumber('lesson')->name('api.admin.lessons.show');
Route::put('lessons/{lesson}', [LessonController::class, 'update'])->whereNumber('lesson')->name('api.admin.lessons.update');
Route::delete('lessons/{lesson}', [LessonController::class, 'destroy'])->whereNumber('lesson')->name('api.admin.lessons.destroy');

Route::post('lessons/{lesson}/attachments', [LessonAttachmentController::class, 'store'])->whereNumber('lesson')->name('api.admin.attachments.store');
Route::get('attachments/{attachment}/download', [LessonAttachmentController::class, 'download'])->whereNumber('attachment')->name('api.admin.attachments.download');
Route::delete('attachments/{attachment}', [LessonAttachmentController::class, 'destroy'])->whereNumber('attachment')->name('api.admin.attachments.destroy');

/* ----------------------------- المستخدمون ----------------------------- */
// نفس قيود can:users.* في routes/web.php؛ وقواعد العمل (حماية super-admin، آخر مدير، حذف النفس) داخل UserController.
Route::get('users', [UserController::class, 'index'])->middleware('can:users.view')->name('api.admin.users.index');
Route::get('users/trashed', [UserController::class, 'trashed'])->middleware('can:users.restore')->name('api.admin.users.trashed');
Route::post('users/{id}/restore', [UserController::class, 'restore'])->whereNumber('id')->middleware('can:users.restore')->name('api.admin.users.restore');
Route::delete('users/{id}/force-delete', [UserController::class, 'forceDelete'])->whereNumber('id')->middleware('can:users.force-delete')->name('api.admin.users.force-delete');
Route::post('users', [UserController::class, 'store'])->middleware('can:users.create')->name('api.admin.users.store');
Route::get('users/{user}', [UserController::class, 'show'])->whereNumber('user')->middleware('can:users.view')->name('api.admin.users.show');
Route::get('users/{user}/edit', [UserController::class, 'edit'])->whereNumber('user')->middleware('can:users.edit')->name('api.admin.users.edit');
Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update'])->whereNumber('user')->middleware('can:users.edit')->name('api.admin.users.update');
Route::delete('users/{user}', [UserController::class, 'destroy'])->whereNumber('user')->middleware('can:users.delete')->name('api.admin.users.destroy');

/* --------------------------- الأدوار والصلاحيات --------------------------- */
Route::get('roles', [A\RbacListController::class, 'roles'])->middleware('permission:roles.view')->name('api.admin.roles.index');
Route::get('roles/create', [RoleController::class, 'create'])->name('api.admin.roles.create');
Route::post('roles', [RoleController::class, 'store'])->name('api.admin.roles.store');
Route::get('roles/{role}', [RoleController::class, 'show'])->whereNumber('role')->name('api.admin.roles.show');
Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->whereNumber('role')->name('api.admin.roles.edit');
Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update'])->whereNumber('role')->name('api.admin.roles.update');
Route::delete('roles/{role}', [RoleController::class, 'destroy'])->whereNumber('role')->name('api.admin.roles.destroy');

Route::get('permissions', [A\RbacListController::class, 'permissions'])->middleware('permission:permissions.view')->name('api.admin.permissions.index');
Route::post('permissions', [PermissionController::class, 'store'])->name('api.admin.permissions.store');
Route::get('permissions/{permission}', [PermissionController::class, 'show'])->whereNumber('permission')->name('api.admin.permissions.show');
Route::match(['put', 'patch'], 'permissions/{permission}', [PermissionController::class, 'update'])->whereNumber('permission')->name('api.admin.permissions.update');
Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->whereNumber('permission')->name('api.admin.permissions.destroy');

/* ------------------- الطلبات والمدفوعات والإيصالات ------------------- */
// الطلبات والمدفوعات سجلات مالية: لا إنشاء ولا تعديل ولا حذف. الصلاحيات داخل متحكمات الـCMS.
Route::get('orders', [A\SalesListController::class, 'orders'])->middleware('permission:orders.view')->name('api.admin.orders.index');
Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order')->name('api.admin.orders.show');
Route::post('orders/{order}/mark-paid', [OrderController::class, 'markPaid'])->whereNumber('order')->name('api.admin.orders.mark-paid');
Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])->whereNumber('order')->name('api.admin.orders.cancel');
Route::post('orders/{order}/refund', [OrderController::class, 'refund'])->whereNumber('order')->name('api.admin.orders.refund');

Route::get('payments', [A\SalesListController::class, 'payments'])->middleware('permission:payments.view')->name('api.admin.payments.index');
Route::get('payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('api.admin.payments.show');

Route::get('receipts', [A\ReceiptAdminController::class, 'index'])->name('api.admin.receipts.index');
Route::get('receipts/{receipt}', [A\ReceiptAdminController::class, 'show'])->whereNumber('receipt')->name('api.admin.receipts.show');
Route::get('receipts/{receipt}/image', [A\ReceiptAdminController::class, 'image'])->whereNumber('receipt')->name('api.admin.receipts.image');
Route::post('receipts/{receipt}/approve', [A\ReceiptAdminController::class, 'approve'])->whereNumber('receipt')->middleware('throttle:30,1')->name('api.admin.receipts.approve');
Route::post('receipts/{receipt}/reject', [A\ReceiptAdminController::class, 'reject'])->whereNumber('receipt')->middleware('throttle:30,1')->name('api.admin.receipts.reject');

/* ------------------------------ الكوبونات ------------------------------ */
Route::get('coupons', [A\SalesListController::class, 'coupons'])->middleware('permission:coupons.view')->name('api.admin.coupons.index');
Route::get('coupons/trashed', [A\SalesListController::class, 'trashedCoupons'])->middleware('permission:coupons.view')->name('api.admin.coupons.trashed');
Route::patch('coupons/{id}/restore', [CouponController::class, 'restore'])->whereNumber('id')->name('api.admin.coupons.restore');
Route::delete('coupons/{id}/force-delete', [CouponController::class, 'forceDelete'])->whereNumber('id')->name('api.admin.coupons.force-delete');
Route::post('coupons', [CouponController::class, 'store'])->name('api.admin.coupons.store');
Route::get('coupons/{coupon}', [CouponController::class, 'show'])->whereNumber('coupon')->name('api.admin.coupons.show');
Route::match(['put', 'patch'], 'coupons/{coupon}', [CouponController::class, 'update'])->whereNumber('coupon')->name('api.admin.coupons.update');
Route::patch('coupons/{coupon}/toggle', [CouponController::class, 'toggle'])->whereNumber('coupon')->name('api.admin.coupons.toggle');
Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->whereNumber('coupon')->name('api.admin.coupons.destroy');

/* -------------------------------- السلات -------------------------------- */
Route::get('carts', [A\SalesListController::class, 'carts'])->middleware('permission:carts.view')->name('api.admin.carts.index');
Route::get('carts/{cart}', [CartController::class, 'show'])->whereNumber('cart')->name('api.admin.carts.show');
Route::delete('carts/{cart}/items', [CartController::class, 'clear'])->whereNumber('cart')->name('api.admin.carts.clear');

/* ------------------------------ لوحة الإحصاءات ------------------------------ */
Route::get('dashboard', A\DashboardController::class)->name('api.admin.dashboard');

/* ------------------------------- التسجيلات ------------------------------- */
// لا حذف للتسجيلات (سجل أكاديمي)؛ الإلغاء يتم بالتعديل.
Route::get('enrollments', [A\OpsListController::class, 'enrollments'])->middleware('permission:enrollments.view')->name('api.admin.enrollments.index');
Route::get('enrollments/lookup/users', [EnrollmentController::class, 'lookupUsers'])->name('api.admin.enrollments.lookup-users');
Route::get('enrollments/create', [EnrollmentController::class, 'create'])->middleware('permission:enrollments.create')->name('api.admin.enrollments.create');
Route::post('enrollments', [EnrollmentController::class, 'store'])->middleware('permission:enrollments.create')->name('api.admin.enrollments.store');
Route::get('enrollments/{enrollment}', [EnrollmentController::class, 'show'])->whereNumber('enrollment')->name('api.admin.enrollments.show');
Route::get('enrollments/{enrollment}/edit', [EnrollmentController::class, 'edit'])->whereNumber('enrollment')->name('api.admin.enrollments.edit');
Route::match(['put', 'patch'], 'enrollments/{enrollment}', [EnrollmentController::class, 'update'])->whereNumber('enrollment')->name('api.admin.enrollments.update');

/* ----------------------- الشهادات وقوالبها ----------------------- */
Route::get('certificate-templates', [A\OpsListController::class, 'certificateTemplates'])->middleware('permission:certificate-templates.view')->name('api.admin.certificate-templates.index');
Route::get('certificate-templates/trashed', [A\OpsListController::class, 'trashedCertificateTemplates'])->middleware('permission:certificate-templates.view')->name('api.admin.certificate-templates.trashed');
Route::patch('certificate-templates/{id}/restore', [CertificateTemplateController::class, 'restore'])->whereNumber('id')->name('api.admin.certificate-templates.restore');
Route::delete('certificate-templates/{id}/force-delete', [CertificateTemplateController::class, 'forceDelete'])->whereNumber('id')->name('api.admin.certificate-templates.force-delete');
Route::post('certificate-templates', [CertificateTemplateController::class, 'store'])->name('api.admin.certificate-templates.store');
Route::get('certificate-templates/{certificate_template}', [CertificateTemplateController::class, 'show'])->whereNumber('certificate_template')->name('api.admin.certificate-templates.show');
Route::get('certificate-templates/{certificate_template}/preview', [CertificateTemplateController::class, 'preview'])->whereNumber('certificate_template')->name('api.admin.certificate-templates.preview');
// الرفع مع التعديل: POST + _method=PUT
Route::match(['put', 'patch'], 'certificate-templates/{certificate_template}', [CertificateTemplateController::class, 'update'])->whereNumber('certificate_template')->name('api.admin.certificate-templates.update');
Route::delete('certificate-templates/{certificate_template}', [CertificateTemplateController::class, 'destroy'])->whereNumber('certificate_template')->name('api.admin.certificate-templates.destroy');

Route::get('certificates', [A\OpsListController::class, 'certificates'])->middleware('permission:certificates.view')->name('api.admin.certificates.index');
Route::get('certificates/eligible', [CertificateController::class, 'eligible'])->name('api.admin.certificates.eligible');
Route::post('certificates', [CertificateController::class, 'store'])->middleware('permission:certificates.create')->name('api.admin.certificates.store');
Route::get('certificates/{certificate}', [CertificateController::class, 'show'])->whereNumber('certificate')->name('api.admin.certificates.show');
Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])->whereNumber('certificate')->name('api.admin.certificates.download');
Route::post('certificates/{certificate}/revoke', [CertificateController::class, 'revoke'])->whereNumber('certificate')->name('api.admin.certificates.revoke');
Route::post('certificates/{certificate}/reinstate', [CertificateController::class, 'reinstate'])->whereNumber('certificate')->name('api.admin.certificates.reinstate');

/* ------------------------------ الجلسات المباشرة ------------------------------ */
Route::get('live-sessions', [A\OpsListController::class, 'liveSessions'])->middleware('permission:live-sessions.view')->name('api.admin.live-sessions.index');
Route::get('live-sessions/trashed', [A\OpsListController::class, 'trashedLiveSessions'])->middleware('permission:live-sessions.view')->name('api.admin.live-sessions.trashed');
Route::get('live-sessions/create', [LiveSessionController::class, 'create'])->middleware('permission:live-sessions.create')->name('api.admin.live-sessions.create');
Route::patch('live-sessions/{id}/restore', [LiveSessionController::class, 'restore'])->whereNumber('id')->name('api.admin.live-sessions.restore');
Route::delete('live-sessions/{id}/force-delete', [LiveSessionController::class, 'forceDelete'])->whereNumber('id')->name('api.admin.live-sessions.force-delete');
Route::post('live-sessions', [LiveSessionController::class, 'store'])->name('api.admin.live-sessions.store');
Route::get('live-sessions/{live_session}', [LiveSessionController::class, 'show'])->whereNumber('live_session')->name('api.admin.live-sessions.show');
Route::get('live-sessions/{live_session}/edit', [LiveSessionController::class, 'edit'])->whereNumber('live_session')->name('api.admin.live-sessions.edit');
Route::match(['put', 'patch'], 'live-sessions/{live_session}', [LiveSessionController::class, 'update'])->whereNumber('live_session')->name('api.admin.live-sessions.update');
Route::post('live-sessions/{live_session}/cancel', [LiveSessionController::class, 'cancel'])->whereNumber('live_session')->name('api.admin.live-sessions.cancel');
Route::delete('live-sessions/{live_session}', [LiveSessionController::class, 'destroy'])->whereNumber('live_session')->name('api.admin.live-sessions.destroy');

/* ------------------------- الاختبارات والأسئلة والمحاولات ------------------------- */
Route::get('quizzes', [A\OpsListController::class, 'quizzes'])->middleware('permission:quizzes.view')->name('api.admin.quizzes.index');
Route::get('quizzes/trashed', [A\OpsListController::class, 'trashedQuizzes'])->middleware('permission:quizzes.view')->name('api.admin.quizzes.trashed');
Route::get('quizzes/create', [QuizController::class, 'create'])->middleware('permission:quizzes.create')->name('api.admin.quizzes.create');
Route::patch('quizzes/{id}/restore', [QuizController::class, 'restore'])->whereNumber('id')->name('api.admin.quizzes.restore');
Route::delete('quizzes/{id}/force-delete', [QuizController::class, 'forceDelete'])->whereNumber('id')->name('api.admin.quizzes.force-delete');
Route::post('quizzes', [QuizController::class, 'store'])->name('api.admin.quizzes.store');
Route::get('quizzes/{quiz}', [QuizController::class, 'show'])->whereNumber('quiz')->name('api.admin.quizzes.show');
Route::get('quizzes/{quiz}/edit', [QuizController::class, 'edit'])->whereNumber('quiz')->name('api.admin.quizzes.edit');
Route::match(['put', 'patch'], 'quizzes/{quiz}', [QuizController::class, 'update'])->whereNumber('quiz')->name('api.admin.quizzes.update');
Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy'])->whereNumber('quiz')->name('api.admin.quizzes.destroy');

Route::get('quizzes/{quiz}/questions', [QuestionController::class, 'index'])->whereNumber('quiz')->name('api.admin.questions.index');
Route::post('quizzes/{quiz}/questions', [QuestionController::class, 'store'])->whereNumber('quiz')->name('api.admin.questions.store');
Route::post('quizzes/{quiz}/questions/reorder', [QuestionController::class, 'reorder'])->whereNumber('quiz')->name('api.admin.questions.reorder');
Route::get('quizzes/{quiz}/questions/trashed', [QuestionController::class, 'trashed'])->whereNumber('quiz')->name('api.admin.questions.trashed');
Route::put('questions/{question}', [QuestionController::class, 'update'])->whereNumber('question')->name('api.admin.questions.update');
Route::delete('questions/{question}', [QuestionController::class, 'destroy'])->whereNumber('question')->name('api.admin.questions.destroy');
Route::patch('questions/{id}/restore', [QuestionController::class, 'restore'])->whereNumber('id')->name('api.admin.questions.restore');
Route::delete('questions/{id}/force-delete', [QuestionController::class, 'forceDelete'])->whereNumber('id')->name('api.admin.questions.force-delete');

Route::get('quiz-attempts', [A\OpsListController::class, 'quizAttempts'])->middleware('permission:quiz-attempts.view')->name('api.admin.quiz-attempts.index');
Route::get('quiz-attempts/{attempt}', [QuizAttemptController::class, 'show'])->whereNumber('attempt')->name('api.admin.quiz-attempts.show');
Route::post('quiz-attempts/{attempt}/void', [QuizAttemptController::class, 'void'])->whereNumber('attempt')->name('api.admin.quiz-attempts.void');

/* ------------------------------ التقدّم ------------------------------ */
Route::get('progress', [A\OpsListController::class, 'progress'])->middleware('permission:progress.view')->name('api.admin.progress.index');
Route::get('progress/{progress}', [CourseProgressController::class, 'show'])->whereNumber('progress')->name('api.admin.progress.show');
Route::post('progress/{progress}/recalculate', [CourseProgressController::class, 'recalculate'])->whereNumber('progress')->name('api.admin.progress.recalculate');

/* ------------------------ الإشعارات (للمراقبة) وسجل التدقيق ------------------------ */
Route::get('notifications', [A\OpsListController::class, 'notifications'])->middleware('permission:notifications.view')->name('api.admin.notifications.index');
Route::get('notifications/{notification}', [NotificationController::class, 'show'])->name('api.admin.notifications.show');
Route::get('audit-logs', [A\OpsListController::class, 'auditLogs'])->middleware('permission:audit-logs.view')->name('api.admin.audit-logs.index');
Route::get('audit-logs/{audit_log}', [AuditLogController::class, 'show'])->whereNumber('audit_log')->name('api.admin.audit-logs.show');
