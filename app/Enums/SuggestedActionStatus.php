<?php

namespace App\Enums;

enum SuggestedActionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Dismissed = 'dismissed';
}
