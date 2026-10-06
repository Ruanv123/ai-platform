<?php

namespace App\Http\Requests\Agents;

use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAgentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('agent')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'objective' => ['required', Rule::enum(AgentObjective::class)],
            'tone' => ['required', Rule::enum(AgentTone::class)],
            'uses_emojis' => ['required', 'boolean'],
            'response_length' => ['required', Rule::enum(AgentResponseLength::class)],
            'welcome_message' => ['required', 'string', 'max:2000'],
            'instructions' => ['nullable', 'array', 'max:50'],
            'instructions.*' => ['required', 'string', 'max:1000'],
            'rules' => ['nullable', 'array', 'max:50'],
            'rules.*' => ['required', 'string', 'max:1000'],
            'collection_ids' => ['nullable', 'array', 'max:100'],
            'collection_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('collections', 'id')->where('team_id', $this->user()?->company()?->getKey()),
            ],
            'schedulable_calendar_ids' => ['nullable', 'array', 'max:100'],
            'schedulable_calendar_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('schedulable_calendars', 'id')->where('team_id', $this->user()?->company()?->getKey()),
            ],
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
            'name.required' => 'Informe o nome da persona.',
            'name.max' => 'O nome da persona deve ter no máximo 255 caracteres.',
            'description.max' => 'A descrição deve ter no máximo 1.000 caracteres.',
            'objective.required' => 'Selecione o objetivo do Agente.',
            'objective.enum' => 'Selecione um objetivo válido.',
            'tone.required' => 'Selecione o tom de voz.',
            'tone.enum' => 'Selecione um tom de voz válido.',
            'uses_emojis.required' => 'Informe se o Agente usará emojis.',
            'uses_emojis.boolean' => 'A opção de emojis é inválida.',
            'response_length.required' => 'Selecione o tamanho das respostas.',
            'response_length.enum' => 'Selecione um tamanho de resposta válido.',
            'welcome_message.required' => 'Informe a mensagem de boas-vindas.',
            'welcome_message.max' => 'A mensagem de boas-vindas deve ter no máximo 2.000 caracteres.',
            'instructions.array' => 'As instruções devem ser uma lista.',
            'instructions.max' => 'Adicione no máximo 50 instruções.',
            'instructions.*.required' => 'Informe cada instrução ou remova o item vazio.',
            'instructions.*.max' => 'Cada instrução deve ter no máximo 1.000 caracteres.',
            'rules.array' => 'As regras devem ser uma lista.',
            'rules.max' => 'Adicione no máximo 50 regras.',
            'rules.*.required' => 'Informe cada regra ou remova o item vazio.',
            'rules.*.max' => 'Cada regra deve ter no máximo 1.000 caracteres.',
            'collection_ids.array' => 'As Coleções selecionadas são inválidas.',
            'collection_ids.max' => 'Selecione no máximo 100 Coleções.',
            'collection_ids.*.uuid' => 'A Coleção selecionada é inválida.',
            'collection_ids.*.distinct' => 'Não selecione a mesma Coleção mais de uma vez.',
            'collection_ids.*.exists' => 'Selecione apenas Coleções da sua Empresa.',
            'schedulable_calendar_ids.array' => 'Os Calendários selecionados são inválidos.',
            'schedulable_calendar_ids.max' => 'Selecione no máximo 100 Calendários.',
            'schedulable_calendar_ids.*.uuid' => 'O Calendário selecionado é inválido.',
            'schedulable_calendar_ids.*.distinct' => 'Não selecione o mesmo Calendário mais de uma vez.',
            'schedulable_calendar_ids.*.exists' => 'Selecione apenas Calendários da sua Empresa.',
        ];
    }
}
