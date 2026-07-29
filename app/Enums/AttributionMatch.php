<?php

namespace App\Enums;

enum AttributionMatch: string
{
    case DiscountCode = 'discount_code';
    case RefLink = 'ref_link';
    case Utm = 'utm';

    public function label(): string
    {
        return match ($this) {
            self::DiscountCode => 'Discount code',
            self::RefLink => 'Referral link',
            self::Utm => 'UTM parameters',
        };
    }
}
