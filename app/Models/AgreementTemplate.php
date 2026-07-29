<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;

#[Fillable([
    'team_id',
    'name',
    'body_markdown',
    'is_default',
])]
class AgreementTemplate extends Model
{
    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The team's default template, materialized from the stock influencer
     * agreement on first use so teams can customize their copy freely.
     */
    public static function defaultFor(Team $team): self
    {
        return static::firstOrCreate(
            ['team_id' => $team->id, 'is_default' => true],
            [
                'name' => 'Influencer Agreement',
                'body_markdown' => File::get(resource_path('templates/influencer-agreement.md')),
            ],
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
