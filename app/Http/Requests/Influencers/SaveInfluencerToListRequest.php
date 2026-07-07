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
        return static::influencerRules();
    }

    /**
     * Validation rules for an influencer payload, optionally nested under a prefix
     * (e.g. 'influencer' when saving to a newly created list).
     *
     * @return array<string, mixed>
     */
    public static function influencerRules(string $prefix = ''): array
    {
        $key = fn (string $field): string => $prefix === '' ? $field : "{$prefix}.{$field}";

        return [
            $key('platform') => ['required', Rule::enum(Platform::class)],
            $key('platform_id') => ['required', 'string', 'max:255'],
            $key('handle') => ['required', 'string', 'max:255'],
            $key('profile_url') => ['required', 'string', 'url', 'max:500'],
            $key('display_name') => ['nullable', 'string', 'max:255'],
            $key('avatar_url') => ['nullable', 'string', 'url', 'max:500'],
            $key('follower_count') => ['nullable', 'integer', 'min:0'],
            $key('engagement_rate') => ['nullable', 'numeric', 'min:0', 'max:100'],
            $key('contact_email') => ['nullable', 'email', 'max:255'],
            $key('latest_activity_at') => ['nullable', 'date'],
        ];
    }
}
