<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
| Laravel 12 reads schedule definitions from this file (the older
| Console\Kernel::schedule() method is no longer auto-invoked).
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

// My Lahab — every minute, fire the 5-min-before reminder push for any
// scheduled checklist items whose time matches now+5min.
Schedule::command('checklist:send-reminders')
    ->everyMinute()
    ->withoutOverlapping(2)
    ->onOneServer();

// My Lahab — purge checklist proof photos older than 24 h hourly.
Schedule::command('checklist:purge-photos')
    ->hourly()
    ->withoutOverlapping(10)
    ->onOneServer();
