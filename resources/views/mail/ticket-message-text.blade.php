{!! $text !!}

--
{{ $ticket->reference() }} · {{ ($brand ?? app(\App\Support\MailBranding::class))->replyHint() }}
@if ($footer = ($brand ?? app(\App\Support\MailBranding::class))->footerText())

{!! $footer !!}
@endif
