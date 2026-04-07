<?php

namespace App\Http\Requests\Influencers;

use App\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInfluencerToListRequest extends FormRequest
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
            'platform' => ['required', Rule::enum(Platform::class)],
            'platform_id' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255'],
            'profile_url' => ['required', 'string', 'url', 'max:500'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'avatar_url' => ['nullable', 'string', 'url', 'max:500'],
            'follower_count' => ['nullable', 'integer', 'min:0'],
            'engagement_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'latest_activity_at' => ['nullable', 'string'],
        ];
    }
}
