<?php

namespace App\Http\Requests\Influencers;

use Illuminate\Foundation\Http\FormRequest;

class SaveInfluencerListRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            // Optional: create the list and add this influencer to it in one request.
            'influencer' => ['sometimes', 'array'],
            ...($this->has('influencer') ? SaveInfluencerToListRequest::influencerRules('influencer') : []),
        ];
    }
}
