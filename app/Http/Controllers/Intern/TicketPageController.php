<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Ticket page: on desktop the list and the selected ticket side by side,
 * on phones either the list or the ticket.
 */
class TicketPageController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        // "Tickets" opens the layout used last (list or board); dashboard links always open the list.
        if ($request->query('darstellung') === 'liste') {
            session(['tickets.layout' => 'list']);
        } elseif (session('tickets.layout') === 'board' && ! $request->has('ansicht')) {
            return redirect()->route('intern.tickets.board');
        }

        return view('intern.tickets.page', ['ticket' => null]);
    }

    public function show(Ticket $ticket): View
    {
        Gate::authorize('view', $ticket);

        return view('intern.tickets.page', ['ticket' => $ticket]);
    }
}
