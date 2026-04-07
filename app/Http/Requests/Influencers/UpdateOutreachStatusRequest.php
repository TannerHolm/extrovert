<?php

namespace App\Http\Requests\Influencers;

use App\Enums\OutreachStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOutreachStatusRequest extends FormRequest
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
            'outreach_status' => ['sometimes', Rule::enum(OutreachStatus::class)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
