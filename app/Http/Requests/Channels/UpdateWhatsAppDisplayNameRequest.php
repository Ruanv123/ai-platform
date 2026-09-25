<?php

namespace App\Http\Requests\Channels;

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppDisplayNameRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:256'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o novo nome de exibição.',
            'name.max' => 'O nome de exibição pode ter no máximo 256 caracteres.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome de exibição'];
    }
}
