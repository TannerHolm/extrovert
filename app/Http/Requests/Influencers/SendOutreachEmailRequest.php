<?php

namespace App\Http\Requests\Influencers;

use Illuminate\Foundation\Http\FormRequest;

class SendOutreachEmailRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ];
    }
}
