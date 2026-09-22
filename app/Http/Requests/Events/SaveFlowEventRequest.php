<?php

namespace App\Http\Requests\Events;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveFlowEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9._-]*$/', Rule::unique('flow_events', 'name')->where('team_id', $this->user()?->company()?->getKey())->ignore($this->route('flowEvent'))],
            'description' => ['nullable', 'string', 'max:1000'],
            'payload_schema' => ['nullable', 'array'],
        ];
    }
}
