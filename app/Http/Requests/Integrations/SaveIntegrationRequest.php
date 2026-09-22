<?php

namespace App\Http\Requests\Integrations;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:64', 'in:agenda,http,crm,ecommerce'],
            'provider' => ['nullable', 'string', 'max:64'],
            'base_url' => ['nullable', 'url:http,https', 'max:2048'],
            'auth_type' => ['nullable', 'in:none,bearer,api_key,basic'],
            'token' => ['nullable', 'string', 'max:4096'],
            'api_key' => ['nullable', 'string', 'max:4096'],
            'api_key_header' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9-]+$/'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:4096'],
            'credentials' => ['sometimes', 'array'],
            'credentials.*' => ['nullable'],
        ];
    }
}
