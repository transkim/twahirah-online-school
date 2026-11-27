<?php

use App\Services\SubscriptionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// End subscriptions whose dates or sessions are used up.
Schedule::call(fn () => app(SubscriptionService::class)->endExpired())->daily();

// Remind students a few days before their plan ends.
Schedule::command('school:renewal-reminders')->dailyAt('08:00')->timezone(config('school.timezone'));
