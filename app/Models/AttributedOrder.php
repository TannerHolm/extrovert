<?php

namespace App\Models;

use App\Enums\AttributionMatch;
use Database\Factories\AttributedOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'team_id',
    'deal_id',
    'shopify_order_id',
    'order_number',
    'total_cents',
    'currency',
    'customer_hash',
    'is_new_customer',
    'matched_via',
    'placed_at',
    'raw_payload',
])]
class AttributedOrder extends Model
{
    /** @use HasFactory<AttributedOrderFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Deal, $this>
     */
    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_new_customer' => 'boolean',
            'matched_via' => AttributionMatch::class,
            'placed_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }
}
