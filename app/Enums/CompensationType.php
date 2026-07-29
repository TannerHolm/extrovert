<?php

namespace App\Enums;

enum CompensationType: string
{
    case Gifted = 'gifted';
    case FlatFee = 'flat_fee';
    case Commission = 'commission';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Gifted => 'Gifted product',
            self::FlatFee => 'Flat fee',
            self::Commission => 'Commission',
            self::Hybrid => 'Hybrid',
        };
    }
}
