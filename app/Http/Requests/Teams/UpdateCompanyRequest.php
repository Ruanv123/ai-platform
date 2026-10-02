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
        if ($this->exists('agent_pause_duration_hours') || $this->exists('agent_pause_duration_minutes_part')) {
            $hours = filter_var($this->input('agent_pause_duration_hours'), FILTER_VALIDATE_INT);
            $minutes = filter_var($this->input('agent_pause_duration_minutes_part'), FILTER_VALIDATE_INT);

            $this->merge([
                'agent_pause_duration_minutes' => $hours === false || $minutes === false
                    ? null
                    : ($hours * 60) + $minutes,
            ]);
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
            'brand_voice' => ['nullable', 'string', 'max:1000'],
            'brand_voice_examples' => ['nullable', 'array', 'min:2', 'max:4'],
            'brand_voice_examples.*' => ['required', 'string', 'max:1000'],
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
            'brand_voice.string' => 'Informe uma descrição válida para a Voz da marca.',
            'brand_voice.max' => 'A descrição da Voz da marca deve ter no máximo 1.000 caracteres.',
            'brand_voice_examples.array' => 'Informe as frases de exemplo em uma lista.',
            'brand_voice_examples.min' => 'Informe de 2 a 4 frases de exemplo.',
            'brand_voice_examples.max' => 'Informe no máximo 4 frases de exemplo.',
            'brand_voice_examples.*.required' => 'Informe cada frase de exemplo ou deixe todos os campos vazios.',
            'brand_voice_examples.*.string' => 'Informe frases de exemplo válidas.',
            'brand_voice_examples.*.max' => 'Cada frase de exemplo deve ter no máximo 1.000 caracteres.',
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
            'brand_voice' => 'Voz da marca',
            'brand_voice_examples' => 'frases de exemplo',
        ];
    }
}
