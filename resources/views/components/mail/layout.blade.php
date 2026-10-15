{{--
    HTML layout of all outgoing mails. Table based with inline styles, because
    many mail clients (Outlook, Gmail) ignore modern CSS. The look comes from
    Admin → Mail-Design (App\Support\MailBranding).
--}}
@props([
    'message' => null,    // Illuminate\Mail\Message while sending (logo is embedded), null in previews
    'reference' => null,  // e.g. "Ticket#2026-00042"
    'preheader' => null,  // preview text in the inbox
    'brand' => null,      // MailBranding with unsaved changes (live preview), else the saved design
])

@php
    $brand ??= app(\App\Support\MailBranding::class);
    $accent = $brand->accent();
    $background = $brand->background();
    $font = $brand->font();
    $logo = $brand->logo($message);
    $logoSize = $brand->logoSize();
    $center = $brand->logoPlacement() === 'center';
    $showName = $brand->showName() || ! $logo;
    $header = $brand->header();
    $radius = $brand->radius();
    $bar = $brand->accentBar();
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
        .footer a { color: #8e8e93; }
        .footer p { margin: 0; }
        .header-text p { margin: 0; }
        @media (max-width: 620px) {
            .card-body { padding: 24px 20px !important; }
            .card-meta { padding: 14px 20px !important; }
            .header-cell { display: block !important; width: 100% !important; text-align: {{ $center ? 'center' : 'left' }} !important; padding: 6px 0 0 !important; }
        }
    </style>
</head>
<body style="margin:0;padding:0;background-color:{{ $background }};-webkit-text-size-adjust:100%;">
    @if ($preheader)
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:{{ $background }};">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;">
                    {{-- Header: logo, company name and optional text --}}
                    <tr>
                        <td style="padding:0 8px 18px;" @if ($center) align="center" @endif>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td class="header-cell" @if ($center) align="center" @endif style="vertical-align:middle;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" @if ($center) align="center" @endif>
                                            <tr>
                                                @if ($logo)
                                                    <td style="{{ $showName ? 'padding-right:12px;' : '' }}vertical-align:middle;">
                                                        <img src="{{ $logo }}" alt="{{ $showName ? '' : $brand->name() }}" height="{{ $logoSize }}" style="display:block;height:{{ $logoSize }}px;width:auto;max-width:200px;border:0;">
                                                    </td>
                                                @endif
                                                @if ($showName)
                                                    <td style="vertical-align:middle;font-family:{{ $font }};font-size:18px;font-weight:600;color:#1d1d1f;letter-spacing:-0.01em;">
                                                        {{ $brand->name() }}
                                                    </td>
                                                @endif
                                            </tr>
                                        </table>
                                    </td>
                                    @if ($header && ! $center)
                                        <td class="header-cell header-text" align="right" style="vertical-align:middle;font-family:{{ $font }};font-size:12px;line-height:1.5;color:#636366;">
                                            {!! \App\Support\MailHtml::inline($header, $accent) !!}
                                        </td>
                                    @endif
                                </tr>
                                @if ($header && $center)
                                    <tr>
                                        <td class="header-text" align="center" style="padding-top:8px;font-family:{{ $font }};font-size:12px;line-height:1.5;color:#636366;">
                                            {!! \App\Support\MailHtml::inline($header, $accent) !!}
                                        </td>
                                    </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    {{-- Card with the message --}}
                    <tr>
                        <td style="background-color:#ffffff;border-radius:{{ $radius }}px;border:1px solid #e5e5ea;overflow:hidden;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                @if ($bar)
                                    <tr><td style="height:4px;line-height:4px;font-size:0;background-color:{{ $accent }};border-radius:{{ $radius }}px {{ $radius }}px 0 0;">&nbsp;</td></tr>
                                @endif
                                <tr>
                                    <td class="card-body content" style="padding:32px 36px;font-family:{{ $font }};font-size:15px;line-height:1.6;color:#1d1d1f;">
                                        {!! \App\Support\MailHtml::inline((string) $slot, $accent) !!}
                                    </td>
                                </tr>
                                @if ($reference)
                                    <tr>
                                        <td class="card-meta" style="padding:16px 36px;border-top:1px solid #e5e5ea;background-color:#fafafa;border-radius:0 0 {{ $radius }}px {{ $radius }}px;font-family:{{ $font }};font-size:13px;line-height:1.5;color:#636366;">
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
                        <td class="footer" align="center" style="padding:22px 16px 0;font-family:{{ $font }};font-size:12px;line-height:1.6;color:#8e8e93;">
                            @if ($footer = $brand->footer())
                                {!! \App\Support\MailHtml::inline($footer, $accent) !!}
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
