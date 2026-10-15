<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use App\Mail\MailDesignTestMail;
use App\Support\Html;
use Illuminate\View\View;

/**
 * Sample ticket mail in the saved mail design (Mail-Design → "In neuem Tab öffnen").
 */
class MailPreviewController extends Controller
{
    public function __invoke(): View
    {
        $ticket = MailDesignTestMail::sampleTicket();
        $body = MailDesignTestMail::sampleBody($ticket);

        return view('mail.ticket-message', ['body' => $body, 'text' => Html::toText($body), 'ticket' => $ticket]);
    }
}
