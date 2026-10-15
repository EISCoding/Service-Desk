<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Open tickets per view, for the overview figures and the views in the sidebar.
 */
final class TicketCounts
{
    /**
     * @return array{mine: int, unread: int, due: int, unassigned: int, all: int}
     */
    public static function for(User $user): array
    {
        $open = fn (): Builder => Ticket::query()->visibleTo($user)->open();

        return [
            'mine' => $open()->where('assignee_id', $user->id)->count(),
            'unread' => $open()->unreadBy($user)->count(),
            'due' => $open()->where('follow_up_due', true)->count(),
            'unassigned' => $open()->whereNull('assignee_id')->count(),
            'all' => $open()->count(),
        ];
    }

    /**
     * Changes whenever one of the counts may have changed (for live updates).
     */
    public static function fingerprint(User $user): string
    {
        return Ticket::fingerprintOf(Ticket::query()->visibleTo($user));
    }
}
