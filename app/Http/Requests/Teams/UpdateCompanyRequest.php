<?php

namespace App\Http\Requests\Teams;

use App\Http\Requests\Teams\Concerns\HasCompanyValidation;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    use HasCompanyValidation;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->company();

        return $user !== null && $team instanceof Team && $user->can('update', $team);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('agent_pause_duration_hours') && ! $this->exists('agent_pause_duration_minutes_part')) {
            return;
        }

        $hours = filter_var($this->input('agent_pause_duration_hours'), FILTER_VALIDATE_INT);
        $minutes = filter_var($this->input('agent_pause_duration_minutes_part'), FILTER_VALIDATE_INT);

        $this->merge([
            'agent_pause_duration_minutes' => $hours === false || $minutes === false
                ? null
                : ($hours * 60) + $minutes,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->companyRules() + [
            'agent_pause_duration_hours' => ['required_with:agent_pause_duration_minutes_part', 'integer', 'min:0', 'max:24'],
            'agent_pause_duration_minutes_part' => ['required_with:agent_pause_duration_hours', 'integer', 'min:0', 'max:59'],
            'agent_pause_duration_minutes' => ['required_with:agent_pause_duration_hours,agent_pause_duration_minutes_part', 'integer', 'between:1,1440'],
        ];
    }

    /**
     * Get custom validation messages in Brazilian Portuguese.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->companyMessages() + [
            'agent_pause_duration_hours.required_with' => 'Informe horas e minutos para a Pausa da IA.',
            'agent_pause_duration_hours.integer' => 'Informe um número inteiro de horas.',
            'agent_pause_duration_hours.min' => 'As horas não podem ser negativas.',
            'agent_pause_duration_hours.max' => 'A Pausa da IA não pode exceder 24 horas.',
            'agent_pause_duration_minutes_part.required_with' => 'Informe horas e minutos para a Pausa da IA.',
            'agent_pause_duration_minutes_part.integer' => 'Informe um número inteiro de minutos.',
            'agent_pause_duration_minutes_part.min' => 'Os minutos não podem ser negativos.',
            'agent_pause_duration_minutes_part.max' => 'Os minutos devem ficar entre 0 e 59.',
            'agent_pause_duration_minutes.required_with' => 'Informe um prazo para a Pausa da IA.',
            'agent_pause_duration_minutes.integer' => 'Informe um prazo válido para a Pausa da IA.',
            'agent_pause_duration_minutes.between' => 'O prazo da Pausa da IA deve ficar entre 1 minuto e 24 horas.',
        ];
    }

    /**
     * Get custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->companyAttributes() + [
            'agent_pause_duration_hours' => 'horas da Pausa da IA',
            'agent_pause_duration_minutes_part' => 'minutos da Pausa da IA',
            'agent_pause_duration_minutes' => 'prazo da Pausa da IA',
        ];
    }
}
