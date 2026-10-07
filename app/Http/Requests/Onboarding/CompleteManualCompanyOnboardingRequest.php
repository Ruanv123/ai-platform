<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\CompanyOnboardingStep;
use App\Enums\TeamSegment;
use App\Enums\Weekday;
use App\Http\Requests\Teams\Concerns\HasBusinessHoursValidation;
use App\Http\Requests\Teams\Concerns\HasCompanyValidation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompleteManualCompanyOnboardingRequest extends FormRequest
{
    use HasBusinessHoursValidation, HasCompanyValidation;

    protected function prepareForValidation(): void
    {
        $businessHours = $this->input('business_hours');

        if (! is_array($businessHours)) {
            return;
        }

        $normalizedBusinessHours = [];

        foreach (Weekday::cases() as $weekday) {
            $schedule = $businessHours[$weekday->value] ?? [];
            $rawEnabled = is_array($schedule) ? ($schedule['enabled'] ?? false) : false;
            $enabled = filter_var($rawEnabled, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            $normalizedBusinessHours[$weekday->value] = [
                'enabled' => $enabled ?? $rawEnabled,
                'open' => $enabled === false ? null : (is_array($schedule) && filled($schedule['open'] ?? null) ? $schedule['open'] : null),
                'close' => $enabled === false ? null : (is_array($schedule) && filled($schedule['close'] ?? null) ? $schedule['close'] : null),
            ];
        }

        $this->merge(['business_hours' => $normalizedBusinessHours]);
    }

    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->company();

        return $user instanceof User
            && $team instanceof Team
            && $team->onboarding_step === CompanyOnboardingStep::ManualCompanyDetails
            && $user->can('update', $team);
    }

    public function rules(): array
    {
        return [
            'segment' => ['required', 'string', Rule::enum(TeamSegment::class)],
            'business_description' => ['required', 'string', 'max:5000'],
            'products_services' => ['required', 'string', 'max:5000'],
            'target_audience' => ['required', 'string', 'max:5000'],
            ...$this->businessHoursRules(),
            'phone' => ['nullable', 'string', 'max:40'],
            'public_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->companyMessages(),
            ...$this->businessHoursMessages(),
        ];
    }

    public function attributes(): array
    {
        return [
            ...$this->companyAttributes(),
            ...$this->businessHoursAttributes(),
        ];
    }
}
