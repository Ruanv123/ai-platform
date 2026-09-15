<?php

namespace App\Http\Requests\Knowledge;

use App\Enums\DocumentType;
use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDocumentRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $document = $this->route('document');

        return $document instanceof Document
            ? $user?->can('update', $document) ?? false
            : $user?->can('create', Document::class) ?? false;
    }

    /**
     * Prepare a document update that omits its unchanged type.
     */
    protected function prepareForValidation(): void
    {
        $document = $this->route('document');

        if (! $this->has('type')) {
            $this->merge([
                'type' => $document instanceof Document
                    ? $document->type->value
                    : DocumentType::Text->value,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $document = $this->route('document');
        $allowedTypes = $document instanceof Document
            ? [$document->type->value]
            : [DocumentType::Text->value, DocumentType::Faq->value, DocumentType::File->value];

        $rules = [
            'type' => ['required', 'string', Rule::in($allowedTypes)],
            'title' => ['required', 'string', 'max:255'],
        ];

        if ($this->input('type') === DocumentType::Faq->value) {
            return array_merge($rules, [
                'items' => ['required', 'array', 'min:1'],
                'items.*' => ['required', 'array:question,answer'],
                'items.*.question' => ['required', 'string'],
                'items.*.answer' => ['required', 'string'],
            ]);
        }

        if ($this->input('type') === DocumentType::File->value) {
            return array_merge($rules, [
                'file' => [
                    $document instanceof Document ? 'nullable' : 'required',
                    'file',
                    'max:'.config('knowledge.max_file_size_kilobytes', 20 * 1024),
                    'mimes:pdf,docx,txt',
                    'extensions:pdf,docx,txt',
                ],
            ]);
        }

        return array_merge($rules, [
            'content' => ['required', 'string'],
        ]);
    }

    /**
     * Get custom validation messages in Brazilian Portuguese.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.in' => 'O tipo de Documento selecionado não é válido.',
            'title.required' => 'Informe o título do Documento.',
            'title.max' => 'O título do Documento deve ter no máximo 255 caracteres.',
            'content.required' => 'Informe o conteúdo do Documento.',
            'items.required' => 'Adicione pelo menos um par de pergunta e resposta.',
            'items.min' => 'Adicione pelo menos um par de pergunta e resposta.',
            'items.*.question.required' => 'Informe a pergunta.',
            'items.*.answer.required' => 'Informe a resposta.',
            'file.required' => 'Selecione um arquivo para enviar.',
            'file.file' => 'O arquivo enviado não é válido.',
            'file.max' => 'O arquivo deve ter no máximo 20 MB.',
            'file.mimes' => 'Envie um arquivo PDF, DOCX ou TXT.',
            'file.extensions' => 'A extensão do arquivo deve ser PDF, DOCX ou TXT.',
        ];
    }
}
