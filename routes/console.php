<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Due-soon and overdue reminders, every working day (spec 06, REQ-06-14).
Schedule::command('app:send-due-reminders')->dailyAt('07:00')->withoutOverlapping();
