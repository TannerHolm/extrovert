<?php

namespace App\Models;

use App\Enums\Platform;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'platform',
    'platform_id',
    'handle',
    'profile_url',
    'display_name',
    'avatar_url',
    'follower_count',
    'engagement_rate',
    'contact_email',
    'latest_activity_at',
    'platform_data',
])]
class Influencer extends Model
{
    /**
     * @return BelongsToMany<InfluencerList, $this>
     */
    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(InfluencerList::class, 'influencer_list_entries')
            ->withPivot(['outreach_status', 'notes', 'added_by'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<InfluencerListEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(InfluencerListEntry::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'follower_count' => 'integer',
            'engagement_rate' => 'decimal:2',
            'latest_activity_at' => 'datetime',
            'platform_data' => 'array',
        ];
    }
}
