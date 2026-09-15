<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamSegment;
use App\Models\Team;
use App\Rules\TeamName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->company();

        if ($this->routeIs('onboarding.company.store')) {
            return $user !== null && $team === null;
        }

        return $user !== null && $team instanceof Team && $user->can('update', $team);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->routeIs('onboarding.company.store') ? 'required' : 'sometimes';
        $weekdays = implode(',', [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday',
        ]);

        return [
            'name' => [$required, 'string', 'max:255', new TeamName],
            'segment' => [$required, 'string', Rule::enum(TeamSegment::class)],
            'website' => ['nullable', 'url:http,https', 'max:2048'],
            'business_description' => [$required, 'string', 'max:5000'],
            'products_services' => [$required, 'string', 'max:5000'],
            'target_audience' => [$required, 'string', 'max:5000'],
            'business_hours' => [$required, "array:{$weekdays}"],
            'business_hours.*' => ['array'],
            'business_hours.*.enabled' => ['sometimes', 'boolean'],
            'business_hours.*.open' => ['nullable', 'date_format:H:i'],
            'business_hours.*.close' => ['nullable', 'date_format:H:i'],
            'timezone' => [$required, 'timezone:all'],
            'phone' => ['nullable', 'string', 'max:40'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom validation messages in Brazilian Portuguese.
     *
     * @return array<string, string>
     */
    public function messages(): array
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
            'business_hours.required' => 'Informe o horário de atendimento.',
            'business_hours.*.open.date_format' => 'Informe a abertura no formato HH:MM.',
            'business_hours.*.close.date_format' => 'Informe o fechamento no formato HH:MM.',
            'timezone.required' => 'Selecione o fuso horário.',
            'timezone.timezone' => 'Selecione um fuso horário válido.',
            'public_email.email' => 'Informe um e-mail público válido.',
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'business_description' => 'descrição do negócio',
            'products_services' => 'produtos e serviços',
            'target_audience' => 'público-alvo',
            'business_hours' => 'horário de atendimento',
            'public_email' => 'e-mail público',
        ];
    }
}
