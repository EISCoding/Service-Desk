{!! $text !!}
@if ($footer = app(\App\Support\MailBranding::class)->footer())

--
{!! $footer !!}
@endif
