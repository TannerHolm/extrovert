<?php

namespace App\Http\Requests\Teams;

use App\Enums\IntegrationProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnectIntegrationRequest extends FormRequest
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
        $rules = [
            'provider' => ['required', Rule::enum(IntegrationProvider::class)],
            'credentials' => ['required', 'array'],
        ];

        $provider = IntegrationProvider::tryFrom((string) $this->input('provider'));

        foreach ($provider?->credentialFields() ?? [] as $field) {
            $rules["credentials.{$field}"] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }
}
