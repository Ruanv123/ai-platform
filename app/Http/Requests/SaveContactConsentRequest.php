<?php

namespace App\Http\Requests;

use App\Models\Contact;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveContactConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->user()?->company();
        $contact = $this->route('contact');

        return $team !== null
            && $contact instanceof Contact
            && $contact->team_id === $team->getKey();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::in(['marketing', 'utility', 'authentication'])],
            'action' => ['required', Rule::in(['granted', 'revoked'])],
            'source' => ['nullable', 'string', 'max:64'],
        ];
    }
}
