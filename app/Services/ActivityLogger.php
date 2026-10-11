<?php

namespace App\Services;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Writes entries to the activity log. Models using the LogsActivity trait
 * are logged automatically; other events (role or group changes, status
 * changes, …) are logged explicitly via log().
 */
class ActivityLogger
{
    private static bool $enabled = true;

    /**
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function log(?Model $subject, string $action, array $changes = [], ?Model $causer = null, ?Model $parent = null): ?Activity
    {
        if (! self::$enabled) {
            return null;
        }

        $causer ??= Auth::user();

        return Activity::create([
            'causer_type' => $causer?->getMorphClass(),
            'causer_id' => $causer?->getKey(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'parent_type' => $parent?->getMorphClass(),
            'parent_id' => $parent?->getKey(),
            'action' => $action,
            'changes' => $changes ?: null,
        ]);
    }

    /**
     * Run the callback without writing activity entries (e.g. seeders, imports).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutLogging(callable $callback): mixed
    {
        $previous = self::$enabled;
        self::$enabled = false;

        try {
            return $callback();
        } finally {
            self::$enabled = $previous;
        }
    }
}
