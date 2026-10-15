<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Remembers when the scheduler last ran (Plesk cron "schedule:run" every minute).
 */
final class SchedulerHeartbeat
{
    private const KEY = 'servicedesk:scheduler-heartbeat';

    public static function beat(): void
    {
        Cache::forever(self::KEY, now()->toIso8601String());
    }

    public static function lastRun(): ?Carbon
    {
        $value = Cache::get(self::KEY);

        return $value ? Carbon::parse($value) : null;
    }

    /**
     * Ran within the last few minutes.
     */
    public static function healthy(): bool
    {
        return (bool) self::lastRun()?->gt(now()->subMinutes(3));
    }
}
