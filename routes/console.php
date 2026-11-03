<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Schedule::command('enrollments:expire')->hourly();
Schedule::command('orders:expire-pending')->hourly();
Schedule::command('quizzes:expire-attempts')->everyFiveMinutes();
Schedule::command('progress:recalculate')->dailyAt('03:30');