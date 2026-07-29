<?php

namespace App\Http\Requests\Influencers;

use App\Enums\CompensationType;
use App\Enums\DealStatus;
use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(DealStatus::class)],
            'compensation_type' => ['required', Rule::enum(CompensationType::class)],
            'flat_fee_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'commission_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'product_value_cents' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'deliverables' => ['nullable', 'array', 'max:20'],
            'deliverables.*.type' => ['required', Rule::in(['post', 'reel', 'video', 'story'])],
            'deliverables.*.platform' => ['required', Rule::enum(Platform::class)],
            'deliverables.*.due_date' => ['nullable', 'date'],
            'deliverables.*.posted_url' => ['nullable', 'url', 'max:2048'],
            'deliverables.*.posted_at' => ['nullable', 'date'],
            'usage_rights' => ['nullable', 'string', 'max:5000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
