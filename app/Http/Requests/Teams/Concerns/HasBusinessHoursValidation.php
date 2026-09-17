<?php

namespace App\Http\Requests\Teams\Concerns;

use App\Enums\Weekday;
use Illuminate\Contracts\Validation\ValidationRule;

trait HasBusinessHoursValidation
{
    /**
     * Get the validation rules for business hours and timezone.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function businessHoursRules(): array
    {
        $weekdays = implode(',', array_column(Weekday::cases(), 'value'));

        return [
            'business_hours' => ['required', "array:{$weekdays}"],
            'business_hours.*' => ['array:enabled,open,close'],
            'business_hours.*.enabled' => ['sometimes', 'boolean'],
            'business_hours.*.open' => ['nullable', 'date_format:H:i'],
            'business_hours.*.close' => ['nullable', 'date_format:H:i'],
            'timezone' => ['required', 'timezone:all'],
        ];
    }

    /**
     * Get the validation messages for business hours and timezone.
     *
     * @return array<string, string>
     */
    protected function businessHoursMessages(): array
    {
        return [
            'business_hours.required' => 'Informe o horário de atendimento.',
            'business_hours.*.open.date_format' => 'Informe a abertura no formato HH:MM.',
            'business_hours.*.close.date_format' => 'Informe o fechamento no formato HH:MM.',
            'timezone.required' => 'Selecione o fuso horário.',
            'timezone.timezone' => 'Selecione um fuso horário válido.',
        ];
    }

    /**
     * Get the custom attribute names for business hours and timezone.
     *
     * @return array<string, string>
     */
    protected function businessHoursAttributes(): array
    {
        return [
            'business_hours' => 'horário de atendimento',
        ];
    }
}
