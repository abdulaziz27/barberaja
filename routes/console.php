<?php

use App\Console\Commands\SendBookingReminderCommand;
use App\Jobs\DeductSubscriptionJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Monthly subscription deduction for Pro tenants
Schedule::job(new DeductSubscriptionJob)->monthly()->at('00:00');

// Daily H-1 booking reminders — runs at 09:00 every day
Schedule::command(SendBookingReminderCommand::class)->dailyAt('09:00');
