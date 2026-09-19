<?php

namespace App\Http\Requests\Knowledge;

use App\Models\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $document = $this->route('document');

        return $document instanceof Document
            && ($this->user()?->can('update', $document) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'collection_id' => [
                'required',
                'uuid',
                Rule::exists('collections', 'id')->where('team_id', $this->user()?->company()?->getKey()),
            ],
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
            'collection_id.required' => 'Selecione uma Coleção.',
            'collection_id.exists' => 'A Coleção selecionada não está disponível.',
        ];
    }
}
