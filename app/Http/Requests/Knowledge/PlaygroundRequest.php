<?php

namespace App\Http\Requests\Knowledge;

use App\Models\Agent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaygroundRequest extends FormRequest
{
    /**
     * Determine whether the user can use this Agent's Playground.
     */
    public function authorize(): bool
    {
        $agent = $this->route('agent');

        return $agent instanceof Agent
            && ($this->user()?->can('view', $agent) ?? false);
    }

    /**
     * Get the validation rules for a disposable conversation turn.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'history' => ['nullable', 'array', 'max:50'],
            'history.*.role' => ['required', Rule::in(['user', 'assistant'])],
            'history.*.content' => ['required', 'string', 'max:10000'],
            'message' => ['required', 'string', 'max:4000'],
        ];
    }

    /**
     * Get user-facing validation messages in Brazilian Portuguese.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'history.array' => 'O histórico da conversa é inválido.',
            'history.max' => 'A conversa atingiu o limite de histórico.',
            'history.*.role.in' => 'O papel da mensagem é inválido.',
            'history.*.content.required' => 'O conteúdo de cada mensagem é obrigatório.',
            'history.*.content.max' => 'Uma mensagem do histórico é longa demais.',
            'message.required' => 'Digite uma mensagem para o Agente.',
            'message.max' => 'A mensagem deve ter no máximo 4.000 caracteres.',
        ];
    }
}
