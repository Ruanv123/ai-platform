<?php

namespace App\Http\Requests\Teams\Concerns;

use App\Enums\TeamSegment;
use App\Rules\TeamName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait HasCompanyValidation
{
    /**
     * Get the validation rules for company details.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function companyRules(string $required = 'sometimes'): array
    {
        return [
            'name' => [$required, 'string', 'max:255', new TeamName],
            'segment' => [$required, 'string', Rule::enum(TeamSegment::class)],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'business_description' => [$required, 'string', 'max:5000'],
            'products_services' => [$required, 'string', 'max:5000'],
            'target_audience' => [$required, 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the validation messages for company details.
     *
     * @return array<string, string>
     */
    protected function companyMessages(): array
    {
        return [
            'name.required' => 'Informe o nome da empresa.',
            'name.max' => 'O nome da empresa deve ter no máximo 255 caracteres.',
            'segment.required' => 'Selecione o segmento da empresa.',
            'segment.enum' => 'Selecione um segmento válido.',
            'website.url' => 'Informe um site válido, começando por http:// ou https://.',
            'business_description.required' => 'Informe uma descrição do negócio.',
            'products_services.required' => 'Informe os produtos ou serviços.',
            'target_audience.required' => 'Informe o público-alvo.',
            'business_description.max' => 'A descrição deve ter no máximo 5.000 caracteres.',
            'products_services.max' => 'Produtos e serviços devem ter no máximo 5.000 caracteres.',
            'target_audience.max' => 'O público-alvo deve ter no máximo 5.000 caracteres.',
            'public_email.email' => 'Informe um e-mail público válido.',
        ];
    }

    /**
     * Get the custom attribute names for company details.
     *
     * @return array<string, string>
     */
    protected function companyAttributes(): array
    {
        return [
            'business_description' => 'descrição do negócio',
            'products_services' => 'produtos e serviços',
            'target_audience' => 'público-alvo',
            'public_email' => 'e-mail público',
        ];
    }
}
