Hi {{ $agreement->signer_name }},

{{ $agreement->team->name }} has prepared a partnership agreement for you to review and sign.

Review and sign it here:
{{ $signUrl }}

The link is unique to you — please don't share it. It expires on {{ $agreement->expires_at?->toFormattedDateString() }}.

If anything in the agreement looks off, just reply to this email.

— {{ $agreement->team->name }}
