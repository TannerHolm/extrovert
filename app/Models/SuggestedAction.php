<?php

namespace App\Models;

use App\Enums\SuggestedActionStatus;
use App\Enums\SuggestedActionType;
use Database\Factories\SuggestedActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'team_id',
    'subject_type',
    'subject_id',
    'type',
    'payload',
    'status',
    'actioned_by',
    'actioned_at',
])]
class SuggestedAction extends Model
{
    /** @use HasFactory<SuggestedActionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * The thing this suggestion acts on — a list entry or a deal.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actioned_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SuggestedActionType::class,
            'status' => SuggestedActionStatus::class,
            'payload' => 'array',
            'actioned_at' => 'datetime',
        ];
    }
}
