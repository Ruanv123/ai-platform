<?php

namespace App\Http\Requests\Templates;

use App\Enums\TeamPermission;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateComponent;
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
        $headerType = $this->input('header_type', $this->existingHeaderFormat());
        $existingHeader = $this->existingHeader();
        $requiresHeaderMedia = in_array($headerType, ['IMAGE', 'DOCUMENT'], true)
            && ($existingHeader === null
                || $existingHeader->format !== strtolower($headerType)
                || blank($existingHeader->media_handle));
        $headerMediaRules = match ($headerType) {
            'IMAGE' => ['file', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:5120'],
            'DOCUMENT' => [
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
                'extensions:pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
                'max:102400',
            ],
            default => ['prohibited'],
        };

        return [
            'whatsapp_account_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'language' => ['required', 'string', 'max:16'],
            'requested_category' => ['required', Rule::in(['marketing', 'utility', 'authentication'])],
            'body' => ['required', 'string', 'max:1024'],
            'header_type' => ['nullable', 'string', Rule::in(['TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'])],
            'header_text' => ['nullable', 'string', 'max:60'],
            'header_media' => [
                $requiresHeaderMedia ? 'required' : 'nullable',
                ...$headerMediaRules,
            ],
            'footer' => ['nullable', 'string', 'max:60'],
            'buttons' => ['nullable', 'array', 'max:3'],
            'buttons.*.type' => ['required_with:buttons', 'string', Rule::in(['QUICK_REPLY', 'URL', 'PHONE_NUMBER', 'COPY_CODE', 'quick_reply', 'url', 'phone_number', 'copy_code'])],
            'buttons.*.text' => ['nullable', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'string', 'max:2000'],
            'buttons.*.phone_number' => ['nullable', 'string', 'max:32'],
            'buttons.*.example' => ['nullable', 'string', 'max:255'],
            'variables' => ['nullable', 'array'],
            'variables.*.position' => ['required_with:variables', 'integer', 'min:1', 'max:10'],
            'variables.*.component' => ['required_with:variables', 'string', Rule::in(['body', 'header'])],
            'variables.*.example' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        $mediaType = $this->input('header_type', $this->existingHeaderFormat());

        return [
            'name.regex' => 'O nome precisa usar apenas letras minúsculas, números e sublinhados.',
            'body.required' => 'Escreva o corpo do Template.',
            'whatsapp_account_id.required' => 'Escolha uma Conta do WhatsApp oficial.',
            'header_media.required' => 'Envie um arquivo de exemplo para o cabeçalho.',
            'header_media.file' => 'Selecione um arquivo de exemplo válido.',
            'header_media.mimes' => $mediaType === 'IMAGE'
                ? 'A imagem de exemplo deve estar no formato JPEG ou PNG.'
                : 'O documento de exemplo deve estar no formato PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT.',
            'header_media.extensions' => $mediaType === 'IMAGE'
                ? 'A imagem de exemplo deve estar no formato JPEG ou PNG.'
                : 'O documento de exemplo deve estar no formato PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT.',
            'header_media.max' => $mediaType === 'IMAGE'
                ? 'A imagem de exemplo deve ter no máximo 5 MB.'
                : 'O documento de exemplo deve ter no máximo 100 MB.',
            'header_media.prohibited' => 'O cabeçalho selecionado não aceita arquivo de exemplo.',
        ];
    }

    private function existingHeader(): ?WhatsAppTemplateComponent
    {
        $template = $this->route('template');

        return $template instanceof WhatsAppTemplate
            ? $template->components()->where('type', 'header')->first()
            : null;
    }

    private function existingHeaderFormat(): string
    {
        return strtoupper((string) ($this->existingHeader()?->format ?? 'TEXT'));
    }

    private function belongsToTeam(WhatsAppTemplate $template): bool
    {
        $teamId = $this->user()?->company()?->getKey();

        return $teamId !== null && $template->account()
            ->where('team_id', $teamId)
            ->exists();
    }
}
