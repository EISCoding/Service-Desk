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
 * - Hidden and encrypted attributes (passwords, IMAP/SMTP credentials) are
 *   logged as changed, but their values are masked.
 * - Values are stored cast (true instead of 1, enum values, dates).
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
        return app(ActivityLogger::class)->log($this, $action, $changes, parent: $this->activityParent());
    }

    /**
     * The model whose history this model's entries also belong to (e.g. the customer of a contact).
     */
    public function activityParent(): ?Model
    {
        return null;
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

            $changes[$key] = [
                'old' => $this->activityValue($key, $old[$key] ?? null),
                'new' => $this->activityValue($key, $value),
            ];
        }

        return $changes;
    }

    /**
     * Turn a raw attribute value into the value stored in the log.
     */
    protected function activityValue(string $key, mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        if (in_array($key, $this->getHidden(), true) || $this->isEncryptedCastable($key)) {
            return Activity::MASKED;
        }

        $value = $this->hasCast($key) ? $this->castAttribute($key, $raw) : $raw;

        return match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => $value,
        };
    }
}
