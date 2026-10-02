<?php

namespace App\Http\Requests\Onboarding;

use App\Models\User;
use App\Rules\SiteReadWebsite;
use App\Rules\TeamName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartCompanyOnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->company() === null;
    }

    public function rules(): array
    {
        return [
            'path' => ['required', Rule::in(['site', 'manual'])],
            'name' => ['required', 'string', 'max:255', new TeamName],
            'website' => [
                'exclude_unless:path,site',
                'required',
                'string',
                'url:http,https',
                'max:2048',
                new SiteReadWebsite,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'path.required' => 'Escolha se deseja ler o site ou preencher os dados manualmente.',
            'path.in' => 'Escolha uma forma válida para configurar a Empresa.',
            'name.required' => 'Informe o nome da empresa.',
            'name.string' => 'Informe um nome de empresa válido.',
            'name.max' => 'O nome da empresa deve ter no máximo 255 caracteres.',
            'website.required' => 'Informe o site da Empresa para iniciar a Leitura.',
            'website.url' => 'Informe um site válido começando por http:// ou https://.',
            'website.max' => 'O endereço do site deve ter no máximo 2.048 caracteres.',
        ];
    }
}
