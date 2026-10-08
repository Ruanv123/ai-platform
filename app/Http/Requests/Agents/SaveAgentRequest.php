<?php

namespace App\Http\Requests\Agents;

use App\Enums\AgentObjective;
use App\Enums\AgentResponseLength;
use App\Enums\AgentTemplateUsage;
use App\Enums\AgentTone;
use App\Enums\WhatsAppTemplateFormat;
use App\Enums\WhatsAppTemplateStatus;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'templates_configured' => ['nullable', 'boolean'],
            'template_links' => ['nullable', 'array', 'max:50'],
            'template_links.*.template_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('whatsapp_templates', 'id')
                    ->whereIn('whatsapp_account_id', $this->teamAccountIds())
                    ->where('status', WhatsAppTemplateStatus::Approved->value)
                    ->where('format', WhatsAppTemplateFormat::Standard->value),
            ],
            'template_links.*.usage' => ['required', Rule::enum(AgentTemplateUsage::class)],
            'reminder_lead_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'follow_up_after_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ];
    }

    /** @return array<int, Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $links = collect($this->input('template_links', []))
                    ->filter(fn (mixed $link): bool => is_array($link)
                        && AgentTemplateUsage::tryFrom((string) ($link['usage'] ?? ''))?->isAutomatic() === true);
                if ($links->isEmpty()) {
                    return;
                }

                $accounts = WhatsAppTemplate::query()
                    ->whereKey($links->pluck('template_id')->filter()->all())
                    ->pluck('whatsapp_account_id', 'id');
                $duplicated = $links
                    ->groupBy(fn (array $link): string => $link['usage'].':'.($accounts[(int) ($link['template_id'] ?? 0)] ?? ''))
                    ->first(fn ($group): bool => $group->count() > 1);

                if ($duplicated !== null) {
                    $validator->errors()->add(
                        'template_links',
                        'Escolha só um Template para "'.AgentTemplateUsage::from($duplicated->first()['usage'])->label().'" em cada Conta do WhatsApp.',
                    );
                }
            },
        ];
    }

    /** @return array<int, int> */
    private function teamAccountIds(): array
    {
        $teamId = $this->user()?->company()?->getKey();

        return $teamId === null ? [] : WhatsAppAccount::query()->where('team_id', $teamId)->pluck('id')->all();
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
            'template_links.max' => 'Escolha no máximo 50 Templates.',
            'template_links.*.template_id.exists' => 'Escolha apenas Templates aprovados, no formato Personalizado, das Contas do WhatsApp da sua Empresa.',
            'template_links.*.template_id.distinct' => 'Não escolha o mesmo Template mais de uma vez.',
            'template_links.*.usage.required' => 'Diga para que o Agente usa cada Template.',
            'reminder_lead_hours.min' => 'A antecedência do lembrete deve ser de pelo menos 1 hora.',
            'reminder_lead_hours.max' => 'A antecedência do lembrete deve ser de no máximo 168 horas.',
            'follow_up_after_hours.min' => 'O follow-up deve esperar pelo menos 1 hora.',
            'follow_up_after_hours.max' => 'O follow-up deve esperar no máximo 168 horas.',
        ];
    }
}
