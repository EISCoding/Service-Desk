<?php

namespace App\Http\Controllers\Intern;

use App\Enums\SystemTemplate;
use App\Http\Controllers\Controller;
use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Support\Html;
use App\Support\Placeholders;
use Illuminate\View\View;

/**
 * Sample ticket mail with the current branding (admin settings → "Vorschau").
 */
class MailPreviewController extends Controller
{
    public function __invoke(): View
    {
        $ticket = new Ticket(['subject' => 'Drucker im Büro druckt nicht', 'requester_name' => 'Erika Muster']);
        $ticket->number = now()->format('Y').'-00042';

        $template = MailTemplate::system(SystemTemplate::TicketOpened);
        $body = (new Placeholders($ticket))->html(Html::sanitize($template->body ?? SystemTemplate::TicketOpened->defaultBody()));

        return view('mail.ticket-message', ['body' => $body, 'text' => Html::toText($body), 'ticket' => $ticket]);
    }
}
