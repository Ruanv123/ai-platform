<?php

namespace App\Http\Requests\Channels;

use App\Models\WhatsAppNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveWhatsAppNumberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $whatsappNumber = $this->route('whatsappNumber');

        if ($user === null) {
            return false;
        }

        return $whatsappNumber instanceof WhatsAppNumber
            ? $user->can('update', $whatsappNumber)
            : $user->can('create', WhatsAppNumber::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $whatsappNumber = $this->route('whatsappNumber');
        $teamId = $this->user()?->company()?->getKey();

        return [
            'waba_id' => ['required', 'string', 'max:255'],
            'phone_number_id' => ['required', 'string', 'max:255'],
            'access_token' => [$whatsappNumber instanceof WhatsAppNumber ? 'nullable' : 'required', 'string', 'max:5000'],
            'app_secret' => [$whatsappNumber instanceof WhatsAppNumber ? 'nullable' : 'required', 'string', 'max:5000'],
            'agent_id' => [
                'nullable',
                'integer',
                Rule::exists('agents', 'id')->where('team_id', $teamId),
            ],
            'team_id' => ['prohibited'],
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
            'team_id.prohibited' => 'A Empresa não pode ser alterada pela requisição.',
        ];
    }

    /**
     * Add validation for the one-number-per-Empresa invariant.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $teamId = $this->user()?->company()?->getKey();
            $currentNumber = $this->route('whatsappNumber');

            if ($teamId === null || ($currentNumber instanceof WhatsAppNumber && $currentNumber->team_id !== $teamId)) {
                return;
            }

            $query = WhatsAppNumber::query()->where('team_id', $teamId);

            if ($currentNumber instanceof WhatsAppNumber) {
                $query->where('id', '!=', $currentNumber->getKey());
            }

            if ($query->exists()) {
                $validator->errors()->add('waba_id', 'Sua Empresa já possui um número de WhatsApp configurado.');
            }
        }];
    }
}
