<?php

namespace App\Http\Requests\Events;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveFlowEventWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url:http,https', 'max:2048'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
