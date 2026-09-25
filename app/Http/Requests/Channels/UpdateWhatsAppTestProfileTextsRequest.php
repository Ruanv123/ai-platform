<?php

namespace App\Http\Requests\Channels;

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWhatsAppTestProfileTextsRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $channel = $this->route('whatsappTestChannel');
        $teamId = $this->user()?->company()?->getKey();

        abort_unless(
            $channel instanceof Channel
                && $channel->type === ChannelType::WhatsAppTest
                && $channel->team_id === $teamId
                && ChannelType::WhatsAppTest->isVisible($this->user()),
            404,
        );
    }

    public function authorize(): bool
    {
        $channel = $this->route('whatsappTestChannel');

        return $channel instanceof Channel
            && ($this->user()?->can('update', $channel) ?? false);
    }

    public function rules(): array
    {
        return [
            'about' => ['required', 'string', 'min:1', 'max:139'],
        ];
    }

    public function messages(): array
    {
        return [
            'about.required' => 'O recado precisa ter pelo menos 1 caractere.',
            'about.min' => 'O recado precisa ter pelo menos 1 caractere.',
            'about.max' => 'O recado pode ter no máximo 139 caracteres.',
            'about.string' => 'O recado precisa ter pelo menos 1 caractere.',
        ];
    }
}
