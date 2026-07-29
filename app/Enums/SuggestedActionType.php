<?php

namespace App\Enums;

enum SuggestedActionType: string
{
    case FirstTouch = 'first_touch';
    case FollowUp = 'follow_up';
    case ReplyTriage = 'reply_triage';
    case DealRecap = 'deal_recap';

    public function label(): string
    {
        return match ($this) {
            self::FirstTouch => 'First outreach',
            self::FollowUp => 'Follow-up',
            self::ReplyTriage => 'Reply triage',
            self::DealRecap => 'Deal recap',
        };
    }

    /**
     * Whether approving this suggestion sends an email.
     */
    public function sendsEmail(): bool
    {
        return in_array($this, [self::FirstTouch, self::FollowUp, self::ReplyTriage]);
    }
}
