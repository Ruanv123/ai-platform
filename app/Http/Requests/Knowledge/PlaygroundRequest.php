<?php

namespace App\Http\Requests\Knowledge;

use App\Http\Requests\Agents\SaveAgentRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class PlaygroundRequest extends SaveAgentRequest
{
    /**
     * Determine whether the user can use the draft Playground.
     */
    public function authorize(): bool
    {
        return $this->user()?->company() !== null;
    }

    /**
     * Get the validation rules for a disposable conversation turn.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'history' => ['nullable', 'array', 'max:50'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:10000'],
            'message' => ['required', 'string', 'max:4000'],
        ]);
    }

    /**
     * Get user-facing validation messages in Brazilian Portuguese.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'history.array' => 'O histórico da conversa é inválido.',
            'history.max' => 'A conversa atingiu o limite de histórico.',
            'history.*.role.in' => 'O papel da mensagem é inválido.',
            'history.*.content.required' => 'O conteúdo de cada mensagem é obrigatório.',
            'history.*.content.max' => 'Uma mensagem do histórico é longa demais.',
            'message.required' => 'Digite uma mensagem para o Agente.',
            'message.max' => 'A mensagem deve ter no máximo 4.000 caracteres.',
        ]);
    }
}
