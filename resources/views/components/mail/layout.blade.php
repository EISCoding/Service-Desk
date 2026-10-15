{{--
    HTML layout of all outgoing mails. Table based with inline styles, because
    many mail clients (Outlook, Gmail) ignore modern CSS.
--}}
@props([
    'message' => null,    // Illuminate\Mail\Message while sending (logo is embedded), null in previews
    'reference' => null,  // e.g. "Ticket#2026-00042"
    'preheader' => null,  // preview text in the inbox
])

@php
    $brand = app(\App\Support\MailBranding::class);
    $accent = $brand->accent();
    $logo = $brand->logo($message);
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
@endphp
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light only">
    <meta name="supported-color-schemes" content="light only">
    <title>{{ $brand->name() }}</title>
    <style>
        .content a { color: {{ $accent }}; }
        .content p, .content div { margin: 0; }
        .content p + p { margin-top: 1em; }
        .content blockquote { margin: 1em 0; padding: 0 0 0 12px; border-left: 3px solid #e5e5ea; color: #636366; }
        .content img { max-width: 100%; height: auto; }
        .content ul, .content ol { margin: 0.5em 0; padding-left: 1.4em; }
        @media (max-width: 620px) {
            .card-body { padding: 24px 20px !important; }
            .card-meta { padding: 14px 20px !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f2f2f7;-webkit-text-size-adjust:100%;">
    @if ($preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f2f2f7;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;">
                    {{-- Header: logo and company name --}}
                    <tr>
                        <td style="padding:0 8px 18px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    @if ($logo)
                                        <td style="padding-right:12px;vertical-align:middle;">
                                            <img src="{{ $logo }}" alt="" height="40" style="display:block;height:40px;width:auto;max-width:160px;border:0;">
                                        </td>
                                    @endif
                                    <td style="vertical-align:middle;font-family:{{ $font }};font-size:18px;font-weight:600;color:#1d1d1f;letter-spacing:-0.01em;">
                                        {{ $brand->name() }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Card with the message --}}
                    <tr>
                        <td style="background-color:#ffffff;border-radius:16px;border:1px solid #e5e5ea;overflow:hidden;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr><td style="height:4px;line-height:4px;font-size:0;background-color:{{ $accent }};border-radius:16px 16px 0 0;">&nbsp;</td></tr>
                                <tr>
                                    <td class="card-body content" style="padding:32px 36px;font-family:{{ $font }};font-size:15px;line-height:1.6;color:#1d1d1f;">
                                        {!! \App\Support\MailHtml::inline((string) $slot, $accent) !!}
                                    </td>
                                </tr>
                                @if ($reference)
                                    <tr>
                                        <td class="card-meta" style="padding:16px 36px;border-top:1px solid #e5e5ea;background-color:#fafafa;border-radius:0 0 16px 16px;font-family:{{ $font }};font-size:13px;line-height:1.5;color:#636366;">
                                            <span style="display:inline-block;padding:2px 8px;border-radius:6px;background-color:#f2f2f7;color:#1d1d1f;font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;">{{ $reference }}</span>
                                            <div style="margin-top:6px;">{{ $brand->replyHint() }}</div>
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="padding:22px 16px 0;font-family:{{ $font }};font-size:12px;line-height:1.6;color:#8e8e93;">
                            @if ($footer = $brand->footer())
                                {!! nl2br(e($footer)) !!}
                            @else
                                {{ $brand->name() }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
