<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $ticket->reference() }}</title>
</head>
<body style="margin:0;padding:24px;background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.55;color:#1d1d1f;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:14px;padding:28px;">
        {!! $body !!}
    </div>
    <p style="max-width:640px;margin:16px auto 0;font-size:12px;color:#6e6e73;">
        {{ $ticket->reference() }} · Bitte antworten Sie direkt auf diese E-Mail, damit Ihre Nachricht dem Ticket zugeordnet wird.
    </p>
</body>
</html>
