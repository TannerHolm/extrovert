Commission summary for {{ $team->name }} — {{ $month->format('F Y') }}

@foreach ($rows as $row)
{{ $row['partner'] }} ({{ $row['handle'] }}): ${{ number_format($row['commission_cents'] / 100, 2) }} owed ({{ rtrim(rtrim(number_format($row['commission_rate'], 2), '0'), '.') }}% of ${{ number_format($row['revenue_cents'] / 100, 2) }} attributed revenue)
@endforeach

Total owed: ${{ number_format($totalCents / 100, 2) }}

The full breakdown is attached as CSV. Figures are computed from Shopify orders attributed to each partner's discount code or referral link during {{ $month->format('F Y') }}.

— Extrovert
