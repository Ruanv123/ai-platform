<?php

namespace App\Http\Requests\Channels;

use App\Models\Channel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveWhatsAppChannelRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $channel = $this->route('channel');

        if ($user === null) {
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
        return [
            'waba_id' => ['required', 'string', 'max:255'],
            'phone_number_id' => ['required', 'string', 'max:255'],
            'access_token' => [$this->route('channel') instanceof Channel ? 'nullable' : 'required', 'string', 'max:5000'],
            'app_secret' => [$this->route('channel') instanceof Channel ? 'nullable' : 'required', 'string', 'max:5000'],
            'agent_id' => [
                'nullable',
                'integer',
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
            'waba_id.required' => 'Informe o WABA ID.',
            'phone_number_id.required' => 'Informe o Phone Number ID.',
            'access_token.required' => 'Informe o access token permanente.',
            'app_secret.required' => 'Informe o App Secret.',
            'agent_id.exists' => 'Selecione apenas um Agente da sua Empresa.',
            'channel_id.prohibited' => 'O Canal não pode ser alterado pela requisição.',
        ];
    }

    /**
     * Add validation for the one-WhatsApp-Channel-per-Empresa invariant.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $teamId = $this->user()?->company()?->getKey();
            $currentChannel = $this->route('channel');

            if ($teamId === null || ($currentChannel instanceof Channel && $currentChannel->team_id !== $teamId)) {
                return;
            }

            $query = Channel::query()
                ->where('team_id', $teamId)
                ->where('type', 'whatsapp');

            if ($currentChannel instanceof Channel) {
                $query->where('id', '!=', $currentChannel->getKey());
            }

            if ($query->exists()) {
                $validator->errors()->add('waba_id', 'Sua Empresa já possui um número de WhatsApp configurado.');
            }
        }];
    }
}
