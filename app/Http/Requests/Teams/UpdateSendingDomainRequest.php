<?php

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSendingDomainRequest extends FormRequest
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
            'sending_from_email' => ['nullable', 'email', 'max:255'],
            'sending_from_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
