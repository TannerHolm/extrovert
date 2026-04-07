<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'name', 'description'])]
class InfluencerList extends Model
{
    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsToMany<Influencer, $this>
     */
    public function influencers(): BelongsToMany
    {
        return $this->belongsToMany(Influencer::class, 'influencer_list_entries')
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
}
