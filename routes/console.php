<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler
|--------------------------------------------------------------------------
|
| Plesk runs `php artisan schedule:run` every minute via cron. There is no
| Supervisor, so the queue is processed here: a short-lived worker starts every
| minute, works through pending jobs and exits when the queue is empty or after
| 50 seconds at the latest. It runs in the background so other scheduled tasks
| are not delayed by it.
|
*/

Schedule::command('queue:work', [
    '--stop-when-empty',
    '--max-time=50',
    '--tries=3',
    '--timeout=60',
])
    ->name('queue-worker')
    ->everyMinute()
    // A crashed worker must not block the queue for long: the lock expires after 10 minutes.
    ->withoutOverlapping(10)
    ->runInBackground();
