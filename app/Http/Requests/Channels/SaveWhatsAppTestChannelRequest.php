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
        $isEditing = $this->route('whatsappTestChannel') instanceof Channel;

        return [
            'name' => $isEditing
                ? ['prohibited']
                : ['required', 'string', 'max:255'],
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
            'name.required' => 'Dê um nome para esta integração.',
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

            if ($teamId === null || $currentChannel instanceof Channel) {
                return;
            }

            $exists = Channel::query()
                ->where('team_id', $teamId)
                ->where('type', ChannelType::WhatsAppTest)
                ->exists();

            if ($exists) {
                $validator->errors()->add('name', 'Sua Empresa já possui um WhatsApp de testes configurado.');
            }
        }];
    }
}
