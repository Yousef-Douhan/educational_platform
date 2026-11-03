<?php

use App\Http\Controllers\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

/*
| تُحمَّل من bootstrap/app.php بلا مجموعة web: لا جلسات ولا كوكيز ولا CSRF.
| المصادقة بالتوقيع فقط، والمعدّل محدود بـ throttle:payment-webhooks.
*/
Route::post('{gateway}', PaymentWebhookController::class)
    ->where('gateway', '[a-z0-9_]{1,30}')
    ->name('webhooks.payment');
