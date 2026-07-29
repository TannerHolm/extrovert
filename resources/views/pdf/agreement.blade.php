<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Agreement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; line-height: 1.55; }
        h1 { font-size: 20px; } h2 { font-size: 15px; margin-top: 18px; }
        hr { border: none; border-top: 1px solid #ccc; margin: 16px 0; }
        .signature-block { margin-top: 32px; padding: 16px; border: 1px solid #888; }
        .signature-name { font-size: 22px; font-family: DejaVu Serif, serif; font-style: italic; margin: 8px 0; }
        .audit { page-break-before: always; }
        .audit table { width: 100%; border-collapse: collapse; font-size: 10px; }
        .audit th, .audit td { border: 1px solid #bbb; padding: 4px 6px; text-align: left; }
        .meta { font-size: 10px; color: #555; }
    </style>
</head>
<body>
    {!! $bodyHtml !!}

    @if ($signed)
        <div class="signature-block">
            <strong>Electronically signed</strong>
            <div class="signature-name">{{ $agreement->signature_payload['typed_name'] ?? $agreement->signer_name }}</div>
            <div class="meta">
                Signed by {{ $agreement->signer_name }} ({{ $agreement->signer_email }})
                on {{ $agreement->signed_at?->toDayDateTimeString() }} UTC<br>
                IP: {{ $agreement->signed_ip }} &middot; Consent to electronic signature: given<br>
                Document SHA-256: {{ $agreement->content_hash }}
            </div>
        </div>

        <div class="audit">
            <h2>Audit trail</h2>
            <table>
                <thead>
                    <tr><th>Event</th><th>Timestamp (UTC)</th><th>IP</th><th>User agent</th></tr>
                </thead>
                <tbody>
                    @foreach ($events as $event)
                        <tr>
                            <td>{{ ucfirst($event->event) }}</td>
                            <td>{{ $event->created_at?->toDateTimeString() }}</td>
                            <td>{{ $event->ip }}</td>
                            <td>{{ Str::limit($event->user_agent ?? '', 60) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
