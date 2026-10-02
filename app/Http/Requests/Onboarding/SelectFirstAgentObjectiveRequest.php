<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\AgentObjective;
use App\Enums\CompanyOnboardingStep;
use App\Models\Agent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectFirstAgentObjectiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->company();

        return $user instanceof User
            && $team instanceof Team
            && $team->onboarding_step === CompanyOnboardingStep::AgentObjective
            && $user->can('update', $team)
            && $user->can('create', Agent::class);
    }

    public function rules(): array
    {
        return [
            'objective' => ['required', 'string', Rule::enum(AgentObjective::class)],
        ];
    }
}
