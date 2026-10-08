<?php

namespace App\Http\Requests;

use App\Models\Channel;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendHumanMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $attributes = [];
        if (! $this->has('content') && $this->has('message')) {
            $attributes['content'] = $this->input('message');
        }
        if (! $this->has('type')) {
            $attributes['type'] = 'text';
        }
        if ($attributes !== []) {
            $this->merge($attributes);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $templateHeaderType = $this->templateHeaderType();
        $templateMediaType = match ($templateHeaderType) {
            'IMAGE' => 'image',
            'DOCUMENT' => 'document',
            default => null,
        };
        $mediaType = $this->input('type') === 'template'
            ? $templateMediaType
            : $this->input('type');
        $mediaRules = match ($mediaType) {
            'image' => ['bail', 'required', 'file', 'mimes:jpeg,jpg,png', 'extensions:jpg,jpeg,png', 'max:5120'],
            'document' => [
                'bail',
                'required',
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
                'extensions:pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
                'max:102400',
            ],
            'video' => ['bail', 'required', 'file', 'mimes:mp4,3gp', 'extensions:mp4,3gp', 'max:16384'],
            'audio' => [
                'bail',
                'required',
                'file',
                'mimetypes:audio/ogg,application/ogg,audio/opus,audio/mpeg,audio/aac,audio/x-aac,audio/mp4,video/mp4',
                'extensions:ogg,opus,mp3,aac,mp4,m4a',
                'max:16384',
            ],
            default => ['nullable', 'prohibited'],
        };
        $templateRules = [
            'nullable',
            'string',
            'max:255',
            'required_if:type,template',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $template = $this->selectedTemplate();
                if ($value !== null && $template !== null && ! $template->status->canSend()) {
                    $fail('Escolha um Template aprovado da Conta do WhatsApp deste Canal.');
                }
                if ($value !== null && $template !== null && ! $template->format->canSendFromConversation()) {
                    $fail('Este formato de Template ainda não pode ser enviado pelo Atendimento.');
                }
                if ($value !== null && $this->templateHeaderType() === 'VIDEO') {
                    $fail('O envio de Templates com cabeçalho de vídeo ainda não está disponível.');
                }
            },
        ];

        return [
            'type' => ['nullable', Rule::in(['text', 'template', 'image', 'document', 'video', 'audio'])],
            'content' => ['nullable', 'string', 'max:4096', 'required_if:type,text'],
            'template' => $templateRules,
            'parameters' => ['nullable', 'array', 'max:20'],
            'parameters.*' => ['string', 'max:1024'],
            'media' => $mediaRules,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $type = $this->input('type') === 'template'
            ? $this->templateHeaderMediaType() ?? 'template'
            : $this->input('type');

        return [
            'media.required' => 'Selecione um arquivo para enviar.',
            'media.file' => 'Selecione um arquivo válido.',
            'media.mimes' => match ($type) {
                'image' => 'A imagem deve estar no formato JPEG ou PNG.',
                'document' => 'O documento deve estar no formato PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT.',
                'video' => 'O vídeo deve estar no formato MP4 ou 3GP.',
                'audio' => 'O áudio deve estar no formato OGG/Opus, MP3, AAC ou MP4.',
                default => 'O arquivo está em um formato não aceito.',
            },
            'media.mimetypes' => match ($type) {
                'audio' => 'O áudio deve estar no formato OGG/Opus, MP3, AAC ou MP4.',
                default => 'O arquivo está em um formato não aceito.',
            },
            'media.extensions' => match ($type) {
                'image' => 'A imagem deve estar no formato JPEG ou PNG.',
                'document' => 'O documento deve estar no formato PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT.',
                'video' => 'O vídeo deve estar no formato MP4 ou 3GP.',
                'audio' => 'O áudio deve estar no formato OGG/Opus, MP3, AAC ou MP4.',
                default => 'O arquivo está em um formato não aceito.',
            },
            'media.max' => match ($type) {
                'image' => 'A imagem deve ter no máximo 5 MB.',
                'document' => 'O documento deve ter no máximo 100 MB.',
                'video' => 'O vídeo deve ter no máximo 16 MB.',
                'audio' => 'O áudio deve ter no máximo 16 MB.',
                default => 'O arquivo excede o tamanho permitido.',
            },
            'media.uploaded' => 'O arquivo não foi enviado. Escolha-o novamente.',
        ];
    }

    private function templateHeaderType(): ?string
    {
        return strtoupper((string) ($this->selectedTemplate()?->componentByType('header')?->format ?? '')) ?: null;
    }

    private function templateHeaderMediaType(): ?string
    {
        return match ($this->templateHeaderType()) {
            'IMAGE' => 'image',
            'DOCUMENT' => 'document',
            default => null,
        };
    }

    private function selectedTemplate(): ?WhatsAppTemplate
    {
        $channel = $this->route('channel');
        if (! $channel instanceof Channel || blank($this->input('template'))) {
            return null;
        }

        $channel->loadMissing('whatsappPhoneNumber');
        $accountId = $channel->whatsappPhoneNumber?->whatsapp_account_id;
        if ($accountId === null) {
            return null;
        }

        return WhatsAppTemplate::query()
            ->where('whatsapp_account_id', $accountId)
            ->where('name', $this->input('template'))
            ->with('components.variables', 'components.buttons')
            ->first();
    }
}
