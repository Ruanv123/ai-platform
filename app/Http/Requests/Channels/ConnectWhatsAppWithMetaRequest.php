<?php

namespace App\Http\Requests\Channels;

use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;

final class ConnectWhatsAppWithMetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Channel::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:5000'],
            'pin' => ['nullable', 'digits:6'],
            'waba_id' => ['prohibited'],
            'phone_number_id' => ['prohibited'],
            'agent_id' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return [
            'pin.digits' => 'Informe os seis dígitos do PIN de confirmação.',
            'waba_id.prohibited' => 'O WABA ID é obtido pela autorização da Meta.',
            'phone_number_id.prohibited' => 'O número é obtido pela autorização da Meta.',
            'agent_id.prohibited' => 'Selecione o Agente depois de conectar o WhatsApp.',
        ];
    }
}
