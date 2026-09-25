<?php

namespace App\Http\Requests\Templates;

use App\Enums\TeamPermission;
use App\Models\WhatsAppTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWhatsAppTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->user()?->company();
        $template = $this->route('template');

        return $team !== null
            && $this->user()->hasTeamPermission($team, TeamPermission::ManageChannels)
            && (! $template instanceof WhatsAppTemplate || $this->belongsToTeam($template));
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'whatsapp_account_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'language' => ['required', 'string', 'max:16'],
            'requested_category' => ['required', Rule::in(['marketing', 'utility', 'authentication'])],
            'body' => ['required', 'string', 'max:1024'],
            'header_type' => ['nullable', 'string', Rule::in(['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'])],
            'header_text' => ['nullable', 'string', 'max:60'],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'buttons.*.type' => ['required_with:buttons', 'string', 'in:QUICK_REPLY,URL,PHONE_NUMBER'],
            'buttons.*.text' => ['required_with:buttons', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'string', 'max:2000'],
            'variables' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => 'O nome precisa usar apenas letras minúsculas, números e sublinhados.',
            'body.required' => 'Escreva o corpo do Template.',
            'whatsapp_account_id.required' => 'Escolha uma Conta do WhatsApp oficial.',
        ];
    }

    private function belongsToTeam(WhatsAppTemplate $template): bool
    {
        $teamId = $this->user()?->company()?->getKey();

        return $teamId !== null && $template->account()
            ->where('team_id', $teamId)
            ->exists();
    }
}
