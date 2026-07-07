<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'direction',
    'from_email',
    'to_email',
    'subject',
    'body',
    'reply_token',
    'message_id',
    'in_reply_to',
    'provider_message_id',
    'status',
    'sent_at',
])]
class OutreachMessage extends Model
{
    public const DIRECTION_OUTBOUND = 'outbound';

    public const DIRECTION_INBOUND = 'inbound';

    /**
     * @return BelongsTo<InfluencerListEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(InfluencerListEntry::class, 'influencer_list_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
