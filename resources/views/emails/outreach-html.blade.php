<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
{{-- Personal-looking outreach: minimal styling, no branded chrome. The body
     is sanitized upstream (App\Support\EmailBody) before it reaches here. --}}
<body style="margin:0; padding:24px; background:#ffffff; color:#1f2937; font-family:-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size:15px; line-height:1.6;">
    <div style="max-width:620px;">
        {!! $bodyHtml !!}
    </div>
</body>
</html>
