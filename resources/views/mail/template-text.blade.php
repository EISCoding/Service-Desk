{!! $text !!}
@if ($footer = ($brand ?? app(\App\Support\MailBranding::class))->footerText())

--
{!! $footer !!}
@endif
