<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Rules\SiteReadWebsite;
use Illuminate\Foundation\Http\FormRequest;

class StartSiteReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->company();

        return $user !== null && $team instanceof Team && $user->can('update', $team);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['website' => $this->user()?->company()?->website]);
    }

    public function rules(): array
    {
        return [
            'website' => ['required', 'string', 'url:http,https', 'max:2048', new SiteReadWebsite],
        ];
    }

    public function messages(): array
    {
        return [
            'website.required' => 'Informe o site da Empresa antes de iniciar a Leitura.',
            'website.url' => 'Informe um site válido começando por http:// ou https://.',
            'website.max' => 'O endereço do site deve ter no máximo 2.048 caracteres.',
        ];
    }
}
