<?php

namespace App\Mail;

use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class MonthlyCommissionReport extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, array{deal_id: int, partner: string, handle: string, commission_rate: float, revenue_cents: int, commission_cents: int}>  $rows
     */
    public function __construct(
        public Team $team,
        public CarbonInterface $month,
        public Collection $rows,
        public string $csv,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Commissions owed for {$this->month->format('F Y')} — {$this->team->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'emails.commission-report',
            with: [
                'team' => $this->team,
                'month' => $this->month,
                'rows' => $this->rows,
                'totalCents' => $this->rows->sum('commission_cents'),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->csv, "commissions-{$this->month->format('Y-m')}.csv")
                ->withMime('text/csv'),
        ];
    }
}
