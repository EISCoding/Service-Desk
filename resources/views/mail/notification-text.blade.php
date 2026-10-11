@if (! empty($greeting))
{{ $greeting }}

@endif
@foreach ($introLines as $line)
{{ $line }}

@endforeach
@isset($actionText)
{{ $actionText }}: {{ $actionUrl }}

@endisset
@foreach ($outroLines as $line)
{{ $line }}

@endforeach
@if (! empty($salutation))
{{ $salutation }}
@endif
@if ($footer = app(\App\Support\MailBranding::class)->footer())

--
{!! $footer !!}
@endif
