<?php

namespace App\Models\Concerns;

use App\Models\Activity;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Logs created/updated/deleted events of a model to the activity log.
 *
 * - Attributes in $activityIgnored (plus timestamps) are not logged.
 * - Hidden attributes (e.g. password) are logged as changed, but masked.
 *
 * @mixin Model
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            $model->logActivity('created', $model->activityChanges(
                array_fill_keys(array_keys($model->getAttributes()), null),
                $model->getAttributes(),
            ));
        });

        static::updated(function (Model $model) {
            $changes = $model->activityChanges(
                array_intersect_key($model->getPrevious(), $model->getChanges()),
                $model->getChanges(),
            );

            if ($changes !== []) {
                $model->logActivity('updated', $changes);
            }
        });

        static::deleted(function (Model $model) {
            $model->logActivity('deleted', $model->activityChanges(
                $model->getAttributes(),
                array_fill_keys(array_keys($model->getAttributes()), null),
            ));
        });
    }

    /**
     * Write a custom entry for this model, e.g. logActivity('roles_changed', ['roles' => ['old' => …, 'new' => …]]).
     *
     * @param  array<string, array{old: mixed, new: mixed}>  $changes
     */
    public function logActivity(string $action, array $changes = []): ?Activity
    {
        return app(ActivityLogger::class)->log($this, $action, $changes);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject')->latest('id');
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @return array<string, array{old: mixed, new: mixed}>
     */
    protected function activityChanges(array $old, array $new): array
    {
        $ignored = [
            ...($this->activityIgnored ?? []),
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
            'remember_token',
        ];

        $changes = [];

        foreach ($new as $key => $value) {
            if (in_array($key, $ignored, true)) {
                continue;
            }

            $masked = in_array($key, $this->getHidden(), true);

            $oldValue = $old[$key] ?? null;

            $changes[$key] = [
                'old' => $masked && $oldValue !== null ? Activity::MASKED : $oldValue,
                'new' => $masked && $value !== null ? Activity::MASKED : $value,
            ];
        }

        return $changes;
    }
}
