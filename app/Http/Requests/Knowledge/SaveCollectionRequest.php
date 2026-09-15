<?php

namespace App\Http\Requests\Knowledge;

use App\Models\Collection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCollectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $collection = $this->route('collection');

        if ($collection instanceof Collection) {
            return $user?->can('update', $collection) ?? false;
        }

        return $user?->can('create', Collection::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $collection = $this->route('collection');
        $teamId = $this->user()?->company()?->getKey();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('collections', 'name')
                    ->where('team_id', $teamId)
                    ->ignore($collection instanceof Collection ? $collection->getKey() : null),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
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
            'name.required' => 'Informe o nome da Coleção.',
            'name.max' => 'O nome da Coleção deve ter no máximo 255 caracteres.',
            'name.unique' => 'Já existe uma Coleção com este nome.',
            'description.max' => 'A descrição deve ter no máximo 5.000 caracteres.',
        ];
    }
}
