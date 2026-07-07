<?php

namespace App\Models;

use App\Enums\OutreachStatus;
use Database\Factories\InfluencerListEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'influencer_list_id',
    'influencer_id',
    'outreach_status',
    'notes',
    'added_by',
])]
class InfluencerListEntry extends Model
{
    /** @use HasFactory<InfluencerListEntryFactory> */
    use HasFactory;

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
     * The outreach message thread for this entry, oldest first.
     *
     * @return HasMany<OutreachMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(OutreachMessage::class)->orderBy('created_at');
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
