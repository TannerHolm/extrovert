<?php

namespace App\Http\Requests\Influencers;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $team = Team::where('slug', $this->route('current_team'))->first();

        $unique = Rule::unique('influencer_lists', 'name')
            ->where('team_id', $team?->id);

        if ($this->route('influencerList')) {
            $unique->ignore($this->route('influencerList'));
        }

        return [
            'name' => ['required', 'string', 'max:255', $unique],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
