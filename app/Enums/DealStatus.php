<?php

namespace App\Enums;

enum DealStatus: string
{
    case Draft = 'draft';
    case Agreed = 'agreed';
    case Live = 'live';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Agreed => 'blue',
            self::Live => 'green',
            self::Completed => 'purple',
            self::Cancelled => 'red',
        };
    }

    /**
     * Statuses representing a deal that is currently in play.
     *
     * @return array<self>
     */
    public static function active(): array
    {
        return [self::Agreed, self::Live];
    }
}
