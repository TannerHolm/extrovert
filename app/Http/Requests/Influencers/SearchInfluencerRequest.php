<?php

namespace App\Http\Requests\Influencers;

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchInfluencerRequest extends FormRequest
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
            'query' => ['required', 'string', 'min:2', 'max:100'],
            'platform' => ['required', Rule::enum(Platform::class)],
            'max_results' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ];
    }
}
