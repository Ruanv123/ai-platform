<?php

namespace App\Http\Requests\Settings;

use App\Models\Agent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkSiteCollectionAgentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Agent::class) ?? false;
    }

    public function rules(): array
    {
        $teamId = $this->user()?->company()?->getKey() ?? -1;

        return [
            'agent_ids' => ['required', 'array', 'min:1'],
            'agent_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('agents', 'id')->where('team_id', $teamId),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'agent_ids.required' => 'Selecione ao menos um Agente.',
            'agent_ids.min' => 'Selecione ao menos um Agente.',
            'agent_ids.*.exists' => 'Selecione apenas Agentes da sua Empresa.',
        ];
    }
}
