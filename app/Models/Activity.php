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

    protected $fillable = ['causer_type', 'causer_id', 'subject_type', 'subject_id', 'action', 'changes'];

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
}
