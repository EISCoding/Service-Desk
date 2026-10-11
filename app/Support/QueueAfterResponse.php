<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;

/**
 * Works off the queue right after the response of a web request that queued
 * jobs (e.g. a reply mail), so mails go out within seconds instead of waiting
 * for the next scheduler minute. PHP-FPM has already sent the response at that
 * point (fastcgi_finish_request), so nobody waits for it. The run is short and
 * ends when the queue is empty; the scheduler's worker remains the safety net.
 */
class QueueAfterResponse
{
    private bool $scheduled = false;

    public function schedule(): void
    {
        if ($this->scheduled || config('queue.default') === 'sync') {
            return;
        }

        $this->scheduled = true;
        app()->terminating(fn () => $this->work());
    }

    public function work(): void
    {
        Artisan::call('queue:work', [
            'connection' => config('queue.default'),
            '--stop-when-empty' => true,
            '--max-time' => 20,
            '--tries' => 3,
            '--timeout' => 60,
        ]);
    }
}
