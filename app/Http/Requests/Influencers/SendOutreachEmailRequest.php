<?php

namespace App\Http\Requests\Influencers;

use App\Support\EmailBody;
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
            // Editor bodies are HTML, so the cap leaves room for markup; the
            // closure rejects markup-only bodies with no actual text.
            'body' => ['required', 'string', 'max:20000', function (string $attribute, mixed $value, \Closure $fail) {
                if (is_string($value) && trim(EmailBody::toText(EmailBody::clean($value))) === '') {
                    $fail(__('The message body cannot be empty.'));
                }
            }],
        ];
    }
}
