<?php

namespace App\Http\Requests\Flows;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'dsl' => ['sometimes', 'array'],
            'nodes' => ['sometimes', 'array'],
            'edges' => ['sometimes', 'array'],
            'channel_id' => ['sometimes', 'nullable', 'uuid'],
            'trigger_type' => ['sometimes', 'in:message,webhook,manual'],
            'trigger_event' => ['sometimes', 'nullable', 'string', 'max:255'],
            'trigger_token' => ['sometimes', 'nullable', 'string', 'max:255'],
            'collision_policy' => ['sometimes', 'in:ignore,queue,replace'],
            'allow_unknown_contact' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('dsl') && ($this->has('nodes') || $this->has('edges'))) {
            $this->merge(['dsl' => [
                'version' => 1,
                'nodes' => $this->input('nodes', []),
                'edges' => $this->input('edges', []),
            ]]);
        }
    }
}
