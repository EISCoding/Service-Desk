{!! $text !!}

--
{{ $ticket->reference() }} · {{ app(\App\Support\MailBranding::class)->replyHint() }}
@if ($footer = app(\App\Support\MailBranding::class)->footer())

{!! $footer !!}
@endif
