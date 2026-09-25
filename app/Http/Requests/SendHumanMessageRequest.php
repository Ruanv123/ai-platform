<?php

namespace App\Http\Requests;

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
        $mediaRules = match ($this->input('type')) {
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
            default => ['nullable', 'prohibited'],
        };

        return [
            'type' => ['nullable', Rule::in(['text', 'template', 'image', 'document', 'video'])],
            'content' => ['nullable', 'string', 'max:4096', 'required_if:type,text'],
            'template' => ['nullable', 'string', 'max:255', 'required_if:type,template'],
            'parameters' => ['nullable', 'array', 'max:20'],
            'parameters.*' => ['string', 'max:1024'],
            'media' => $mediaRules,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $type = $this->input('type');

        return [
            'media.required' => 'Selecione um arquivo para enviar.',
            'media.file' => 'Selecione um arquivo válido.',
            'media.mimes' => match ($type) {
                'image' => 'A imagem deve estar no formato JPEG ou PNG.',
                'document' => 'O documento deve estar no formato PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT.',
                'video' => 'O vídeo deve estar no formato MP4 ou 3GP.',
                default => 'O arquivo está em um formato não aceito.',
            },
            'media.extensions' => match ($type) {
                'image' => 'A imagem deve estar no formato JPEG ou PNG.',
                'document' => 'O documento deve estar no formato PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT.',
                'video' => 'O vídeo deve estar no formato MP4 ou 3GP.',
                default => 'O arquivo está em um formato não aceito.',
            },
            'media.max' => match ($type) {
                'image' => 'A imagem deve ter no máximo 5 MB.',
                'document' => 'O documento deve ter no máximo 100 MB.',
                'video' => 'O vídeo deve ter no máximo 16 MB.',
                default => 'O arquivo excede o tamanho permitido.',
            },
            'media.uploaded' => 'O arquivo não foi enviado. Escolha-o novamente.',
        ];
    }
}
