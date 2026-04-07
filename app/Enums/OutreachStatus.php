<?php

namespace App\Enums;

enum OutreachStatus: string
{
    case None = 'none';
    case Contacted = 'contacted';
    case Replied = 'replied';
    case Negotiating = 'negotiating';
    case Confirmed = 'confirmed';
    case Declined = 'declined';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::None => 'gray',
            self::Contacted => 'blue',
            self::Replied => 'yellow',
            self::Negotiating => 'orange',
            self::Confirmed => 'green',
            self::Declined => 'red',
        };
    }
}
