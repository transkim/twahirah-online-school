<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// End subscriptions whose dates or sessions are used up.
\Illuminate\Support\Facades\Schedule::call(fn () => app(\App\Services\SubscriptionService::class)->endExpired())->daily();
