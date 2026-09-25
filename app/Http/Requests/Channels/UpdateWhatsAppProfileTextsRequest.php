<?php

namespace App\Http\Requests\Channels;

use App\Enums\ChannelType;
use App\Enums\WhatsAppBusinessCategory;
use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWhatsAppProfileTextsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $channel = $this->route('channel');
        $teamId = $this->user()?->company()?->getKey();

        abort_unless(
            $channel instanceof Channel
                && $channel->type === ChannelType::WhatsApp
                && $channel->team_id === $teamId,
            404,
        );
    }

    public function authorize(): bool
    {
        $channel = $this->route('channel');

        return $channel instanceof Channel
            && ($this->user()?->can('update', $channel) ?? false);
    }

    public function rules(): array
    {
        return [
            'about' => ['sometimes', 'string', 'min:1', 'max:139'],
            'description' => ['sometimes', 'nullable', 'string', 'max:512'],
            'address' => ['sometimes', 'nullable', 'string', 'max:256'],
            'email' => ['sometimes', 'nullable', 'email', 'max:128'],
            'websites' => ['sometimes', 'array', 'max:2'],
            'websites.*' => ['required', 'string', 'max:256', 'url:http,https'],
            'category' => ['sometimes', 'string', Rule::enum(WhatsAppBusinessCategory::class)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isEmpty()
                && array_intersect(['about', 'description', 'address', 'email', 'websites', 'category'], array_keys($this->all())) === []) {
                $validator->errors()->add('about', 'Altere pelo menos um campo do Perfil antes de salvar.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'about.min' => 'O recado precisa ter pelo menos 1 caractere.',
            'about.max' => 'O recado pode ter no máximo 139 caracteres.',
            'about.string' => 'O recado precisa ter pelo menos 1 caractere.',
            'description.max' => 'A descrição pode ter no máximo 512 caracteres.',
            'address.max' => 'O endereço pode ter no máximo 256 caracteres.',
            'email.email' => 'Informe um e-mail válido.',
            'email.max' => 'O e-mail pode ter no máximo 128 caracteres.',
            'websites.max' => 'Informe no máximo dois sites.',
            'websites.*.max' => 'Cada site pode ter no máximo 256 caracteres.',
            'websites.*.url' => 'Informe um endereço de site válido com http ou https.',
            'category.enum' => 'Selecione uma categoria válida.',
        ];
    }

    public function attributes(): array
    {
        return [
            'about' => 'recado',
            'description' => 'descrição',
            'address' => 'endereço',
            'email' => 'e-mail',
            'websites' => 'sites',
            'category' => 'categoria',
        ];
    }
}
