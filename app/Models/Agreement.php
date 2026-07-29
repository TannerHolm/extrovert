<?php

namespace App\Models;

use App\Enums\AgreementStatus;
use Database\Factories\AgreementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'team_id',
    'deal_id',
    'status',
    'body_markdown',
    'pdf_path',
    'signed_pdf_path',
    'content_hash',
    'sign_token',
    'signer_name',
    'signer_email',
    'sent_at',
    'viewed_at',
    'signed_at',
    'expires_at',
    'signature_payload',
    'signed_ip',
    'signed_user_agent',
    'created_by',
])]
class Agreement extends Model
{
    /** @use HasFactory<AgreementFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Agreement $agreement) {
            if (empty($agreement->sign_token)) {
                $agreement->sign_token = Str::random(64);
            }
        });
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AgreementEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AgreementEvent::class)->orderBy('created_at');
    }

    /**
     * Append to the audit trail. The trail (plus hash, consent, IP/UA and
     * timestamps) is what gives the e-signature its legal footing.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function recordEvent(string $event, ?string $ip = null, ?string $userAgent = null, ?array $meta = null): AgreementEvent
    {
        return $this->events()->create([
            'event' => $event,
            'ip' => $ip,
            'user_agent' => $userAgent,
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgreementStatus::class,
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
            'expires_at' => 'datetime',
            'signature_payload' => 'array',
        ];
    }
}
