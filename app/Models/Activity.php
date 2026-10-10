<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One entry of the activity log: who (causer) did what (action) to which
 * object (subject), with the changed attributes as JSON.
 *
 * @property array<string, array{old: mixed, new: mixed}>|null $changes
 */
class Activity extends Model
{
    /** Value stored instead of hidden attributes such as passwords. */
    public const MASKED = '********';

    public const UPDATED_AT = null;

    protected $table = 'activity_log';

    protected $fillable = ['causer_type', 'causer_id', 'subject_type', 'subject_id', 'parent_type', 'parent_id', 'action', 'changes'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Entries of a model's history: its own entries and those of its children
     * (e.g. a customer's contacts and notes).
     */
    public function scopeForHistoryOf($query, Model $model): void
    {
        $query->where(fn ($query) => $query
            ->where(fn ($query) => $query->where('subject_type', $model->getMorphClass())->where('subject_id', $model->getKey()))
            ->orWhere(fn ($query) => $query->where('parent_type', $model->getMorphClass())->where('parent_id', $model->getKey())));
    }
}
