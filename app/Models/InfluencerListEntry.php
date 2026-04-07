<?php

namespace App\Models;

use App\Enums\OutreachStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'influencer_list_id',
    'influencer_id',
    'outreach_status',
    'notes',
    'added_by',
])]
class InfluencerListEntry extends Model
{
    /**
     * @return BelongsTo<InfluencerList, $this>
     */
    public function influencerList(): BelongsTo
    {
        return $this->belongsTo(InfluencerList::class);
    }

    /**
     * @return BelongsTo<Influencer, $this>
     */
    public function influencer(): BelongsTo
    {
        return $this->belongsTo(Influencer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outreach_status' => OutreachStatus::class,
        ];
    }
}
