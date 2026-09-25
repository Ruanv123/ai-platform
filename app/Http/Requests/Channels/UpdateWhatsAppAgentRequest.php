<?php

namespace App\Http\Requests\Channels;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWhatsAppAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $channel = $this->route('channel');

        return $channel instanceof Channel
            && ($this->user()?->can('update', $channel) ?? false);
    }

    public function rules(): array
    {
        return [
            'agent_id' => [
                'nullable',
                'uuid',
                Rule::exists('agents', 'id')->where('team_id', $this->user()?->company()?->getKey()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'agent_id.exists' => 'Selecione apenas um Agente da sua Empresa.',
        ];
    }
}
