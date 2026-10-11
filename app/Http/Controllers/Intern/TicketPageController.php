<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Ticket page: on desktop the list and the selected ticket side by side,
 * on phones either the list or the ticket.
 */
class TicketPageController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Ticket::class);

        return view('intern.tickets.page', ['ticket' => null]);
    }

    public function show(Ticket $ticket): View
    {
        Gate::authorize('view', $ticket);

        return view('intern.tickets.page', ['ticket' => $ticket]);
    }
}
