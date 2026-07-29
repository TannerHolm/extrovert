<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'from_email',
    'to_email',
    'subject',
    'body',
    'raw_payload',
    'received_at',
    'reviewed_at',
])]
class UnmatchedInboundEmail extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
