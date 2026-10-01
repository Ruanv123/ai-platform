<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\CompanyOnboardingStep;
use App\Enums\SiteReadProposalStatus;
use App\Enums\TeamSegment;
use App\Enums\Weekday;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfirmSiteProposalOnboardingRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $businessHours = $this->input('business_hours');

        if (is_array($businessHours)) {
            $normalizedBusinessHours = [];
            $hasSchedule = false;

            foreach (Weekday::cases() as $weekday) {
                $schedule = $businessHours[$weekday->value] ?? [];
                $rawEnabled = is_array($schedule) ? ($schedule['enabled'] ?? false) : false;
                $enabled = filter_var($rawEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $open = is_array($schedule) && filled($schedule['open'] ?? null) ? $schedule['open'] : null;
                $close = is_array($schedule) && filled($schedule['close'] ?? null) ? $schedule['close'] : null;

                if ($enabled === true || $enabled === null || $open !== null || $close !== null) {
                    $hasSchedule = true;
                }

                $normalizedBusinessHours[$weekday->value] = [
                    'enabled' => $enabled ?? $rawEnabled,
                    'open' => $enabled === false ? null : $open,
                    'close' => $enabled === false ? null : $close,
                ];
            }

            if (! $hasSchedule) {
                $this->merge(['business_hours' => null]);
            } else {
                $this->merge(['business_hours' => $normalizedBusinessHours]);
            }
        }

        $examples = $this->input('brand_voice_examples');

        if (is_array($examples)) {
            $examples = array_values(array_filter(
                $examples,
                static fn (mixed $example): bool => $example !== null && ! (is_string($example) && blank($example)),
            ));

            $this->merge(['brand_voice_examples' => $examples === [] ? null : $examples]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->company();

        return $user instanceof User
            && $team instanceof Team
            && $team->onboarding_step === CompanyOnboardingStep::ProposalReview
            && $team->siteReadProposals()
                ->where('status', SiteReadProposalStatus::Pending->value)
                ->exists()
            && $user->can('update', $team);
    }

    public function rules(): array
    {
        return [
            'segment' => ['nullable', 'string', Rule::enum(TeamSegment::class)],
            'business_description' => ['nullable', 'string', 'max:5000'],
            'products_services' => ['nullable', 'string', 'max:5000'],
            'target_audience' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'business_hours' => ['nullable', 'array:'.implode(',', array_column(Weekday::cases(), 'value'))],
            'business_hours.*' => ['array:enabled,open,close'],
            'business_hours.*.enabled' => ['sometimes', 'boolean'],
            'business_hours.*.open' => ['nullable', 'date_format:H:i'],
            'business_hours.*.close' => ['nullable', 'date_format:H:i'],
            'brand_voice' => ['nullable', 'string', 'max:1000'],
            'brand_voice_examples' => ['nullable', 'array', 'min:2', 'max:4'],
            'brand_voice_examples.*' => ['required', 'string', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $brandVoice = $this->input('brand_voice');
            $examples = $this->input('brand_voice_examples');

            if (filled($brandVoice) && ! is_array($examples)) {
                $validator->errors()->add('brand_voice_examples', 'Informe de 2 a 4 frases de exemplo da Voz da marca.');
            }

            if (! filled($brandVoice) && is_array($examples)) {
                $validator->errors()->add('brand_voice', 'Informe a descrição da Voz da marca junto com as frases de exemplo.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'segment.enum' => 'Selecione um segmento válido.',
            'business_description.max' => 'A descrição deve ter no máximo 5.000 caracteres.',
            'products_services.max' => 'Produtos e serviços devem ter no máximo 5.000 caracteres.',
            'target_audience.max' => 'O público-alvo deve ter no máximo 5.000 caracteres.',
            'phone.max' => 'O telefone público deve ter no máximo 40 caracteres.',
            'public_email.email' => 'Informe um e-mail público válido.',
            'public_email.max' => 'O e-mail público deve ter no máximo 255 caracteres.',
            'address.max' => 'O endereço deve ter no máximo 1.000 caracteres.',
            'business_hours.*.open.date_format' => 'Informe a abertura no formato HH:MM.',
            'business_hours.*.close.date_format' => 'Informe o fechamento no formato HH:MM.',
            'brand_voice.max' => 'A descrição da Voz da marca deve ter no máximo 1.000 caracteres.',
            'brand_voice_examples.array' => 'Informe as frases de exemplo em uma lista.',
            'brand_voice_examples.max' => 'Informe no máximo 4 frases de exemplo.',
            'brand_voice_examples.*.required' => 'Informe cada frase de exemplo ou deixe todos os campos vazios.',
            'brand_voice_examples.*.string' => 'Informe frases de exemplo válidas.',
            'brand_voice_examples.*.max' => 'Cada frase de exemplo deve ter no máximo 1.000 caracteres.',
        ];
    }
}
