<?php

namespace App\Http\Requests\Channels;

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveWhatsAppTestChannelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $channel = $this->route('whatsappTestChannel');

        if ($user === null || ! ChannelType::WhatsAppTest->isVisible($user)) {
            return false;
        }

        return $channel instanceof Channel
            ? $user->can('update', $channel)
            : $user->can('create', Channel::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $channel = $this->route('whatsappTestChannel');

        return [
            'service_url' => ['required', 'url:http,https', 'max:2048'],
            'instance_name' => ['required', 'string', 'max:255'],
            'access_token' => [$channel instanceof Channel ? 'nullable' : 'required', 'string', 'max:5000'],
            'agent_id' => [
                'nullable',
                'uuid',
                Rule::exists('agents', 'id')->where('team_id', $this->user()?->company()?->getKey()),
            ],
            'channel_id' => ['prohibited'],
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
            'service_url.required' => 'Informe o endereço do serviço.',
            'service_url.url' => 'Informe um endereço HTTP ou HTTPS válido.',
            'instance_name.required' => 'Informe o nome da instância.',
            'access_token.required' => 'Informe a credencial de acesso.',
            'agent_id.exists' => 'Selecione apenas um Agente da sua Empresa.',
            'channel_id.prohibited' => 'O Canal não pode ser alterado pela requisição.',
        ];
    }

    /**
     * Add validation for the one-test-channel-per-company invariant.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $teamId = $this->user()?->company()?->getKey();
            $currentChannel = $this->route('whatsappTestChannel');

            if ($teamId === null || ($currentChannel instanceof Channel && $currentChannel->team_id !== $teamId)) {
                return;
            }

            $query = Channel::query()
                ->where('team_id', $teamId)
                ->where('type', ChannelType::WhatsAppTest);

            if ($currentChannel instanceof Channel) {
                $query->where('id', '!=', $currentChannel->getKey());
            }

            if ($query->exists()) {
                $validator->errors()->add('service_url', 'Sua Empresa já possui um WhatsApp de testes configurado.');
            }
        }];
    }
}
