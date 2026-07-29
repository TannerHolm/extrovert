<?php

namespace App\Models;

use App\Enums\CompensationType;
use App\Enums\DealStatus;
use Database\Factories\DealFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'team_id',
    'influencer_list_entry_id',
    'status',
    'compensation_type',
    'flat_fee_cents',
    'commission_rate',
    'product_value_cents',
    'deliverables',
    'usage_rights',
    'starts_at',
    'ends_at',
    'notes',
    'discount_code',
    'shopify_price_rule_id',
    'shopify_discount_code_id',
    'ref_token',
    'created_by',
])]
class Deal extends Model
{
    /** @use HasFactory<DealFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Shopify orders attributed to this deal.
     *
     * @return HasMany<AttributedOrder, $this>
     */
    public function attributedOrders(): HasMany
    {
        return $this->hasMany(AttributedOrder::class);
    }

    /**
     * Agreements drafted for this deal, newest first.
     *
     * @return HasMany<Agreement, $this>
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class)->orderByDesc('created_at');
    }

    /**
     * Deals that are currently in play (agreed or live).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', DealStatus::active());
    }

    /**
     * Deliverables whose due date has passed without a posted URL. Only meaningful
     * while the deal is in play — draft and terminal deals never report overdue work.
     *
     * @return array<int, array<string, mixed>>
     */
    public function overdueDeliverables(): array
    {
        if (! in_array($this->status, DealStatus::active())) {
            return [];
        }

        return collect($this->deliverables ?? [])
            ->filter(fn (array $deliverable) => empty($deliverable['posted_url'])
                && ! empty($deliverable['due_date'])
                && Carbon::parse($deliverable['due_date'])->isPast())
            ->values()
            ->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DealStatus::class,
            'compensation_type' => CompensationType::class,
            'deliverables' => 'array',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }
}
