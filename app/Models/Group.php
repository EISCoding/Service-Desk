<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Team of staff members (e.g. "Support", "Technik"). Tickets are assigned to
 * groups; members see the tickets of their groups (tickets.view.group).
 */
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'description'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
