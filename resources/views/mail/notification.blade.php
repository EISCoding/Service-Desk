{{-- Laravel notification mails (MailMessage) in the branded layout --}}
@php($accent = app(\App\Support\MailBranding::class)->accent())
<x-mail.layout :message="$message ?? null" :preheader="$introLines[0] ?? null">
    @if (! empty($greeting))
        <p style="margin:0 0 1em;font-weight:600;">{{ $greeting }}</p>
    @endif

    @foreach ($introLines as $line)
        <p style="margin:0 0 1em;">{{ $line }}</p>
    @endforeach

    @isset($actionText)
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:8px 0 24px;">
            <tr>
                <td style="border-radius:10px;background-color:{{ $accent }};">
                    <a href="{{ $actionUrl }}" style="display:inline-block;padding:11px 22px;border-radius:10px;font-weight:600;font-size:15px;color:#ffffff;text-decoration:none;">{{ $actionText }}</a>
                </td>
            </tr>
        </table>
    @endisset

    @foreach ($outroLines as $line)
        <p style="margin:0 0 1em;">{{ $line }}</p>
    @endforeach

    @if (! empty($salutation))
        <p style="margin:1.5em 0 0;">{!! nl2br(e($salutation)) !!}</p>
    @endif

    @isset($actionText)
        <p style="margin:2em 0 0;font-size:12px;color:#8e8e93;">Falls der Button nicht funktioniert, diese Adresse im Browser öffnen:<br><a href="{{ $actionUrl }}" style="color:#8e8e93;word-break:break-all;">{{ $displayableActionUrl }}</a></p>
    @endisset
</x-mail.layout>
