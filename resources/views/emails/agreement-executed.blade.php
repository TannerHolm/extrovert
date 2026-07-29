The partnership agreement between {{ $agreement->team->name }} and {{ $agreement->signer_name }} has been signed by all parties.

Signed by: {{ $agreement->signer_name }} ({{ $agreement->signer_email }})
Signed at: {{ $agreement->signed_at?->toDayDateTimeString() }} UTC

A copy of the executed agreement is attached for your records.

— {{ $agreement->team->name }}
