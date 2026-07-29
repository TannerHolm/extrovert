<?php

namespace App\Enums;

enum AgreementStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Viewed = 'viewed';
    case Signed = 'signed';
    case Declined = 'declined';
    case Voided = 'voided';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Sent => 'blue',
            self::Viewed => 'yellow',
            self::Signed => 'green',
            self::Declined => 'red',
            self::Voided => 'red',
        };
    }

    /**
     * Whether the signing link should still work for this status.
     */
    public function isSignable(): bool
    {
        return in_array($this, [self::Sent, self::Viewed]);
    }
}
