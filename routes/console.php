<?php

use App\Jobs\MarkDueFollowUps;
use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler
|--------------------------------------------------------------------------
|
| Plesk runs `php artisan schedule:run` every minute via cron. There is no
| Supervisor, so the queue is processed here: a worker starts every minute,
| checks for jobs every 3 seconds and exits after 55 seconds, so there is never
| a permanent process. It runs in the background so other tasks are not delayed.
| Web requests additionally work off their own jobs right after the response
| (App\Support\QueueAfterResponse).
|
*/

Schedule::command('queue:work', [
    '--max-time=55',
    '--sleep=3',
    '--tries=3',
    '--timeout=60',
])
    ->name('queue-worker')
    ->everyMinute()
    // A crashed worker must not block the queue for long: the lock expires after 10 minutes.
    ->withoutOverlapping(10)
    ->runInBackground();

// Wiedervorlage: flag tickets whose follow-up date has come (processed by the queue worker above).
Schedule::job(new MarkDueFollowUps)
    ->name('mark-due-follow-ups')
    ->dailyAt('06:00');

// Mail: fetch every 20 seconds, in the scheduler process itself (schedule:run
// keeps running for the minute; no extra process is started for this).
Schedule::call(fn () => Artisan::call('servicedesk:fetch-mail', ['--now' => true]))
    ->name('fetch-mail')
    ->everyTwentySeconds()
    ->withoutOverlapping(5);

// Heartbeat: the admin area shows whether the Plesk cron task (schedule:run) is running.
Schedule::call(fn () => SchedulerHeartbeat::beat())
    ->name('heartbeat')
    ->everyMinute();
