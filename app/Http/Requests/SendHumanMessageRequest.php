<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendHumanMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $attributes = [];
        if (! $this->has('content') && $this->has('message')) {
            $attributes['content'] = $this->input('message');
        }
        if (! $this->has('type')) {
            $attributes['type'] = 'text';
        }
        if ($attributes !== []) {
            $this->merge($attributes);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['text', 'template'])],
            'content' => ['nullable', 'string', 'max:4096', 'required_if:type,text'],
            'template' => ['nullable', 'string', 'max:255', 'required_if:type,template'],
            'parameters' => ['nullable', 'array', 'max:20'],
            'parameters.*' => ['string', 'max:1024'],
        ];
    }
}
