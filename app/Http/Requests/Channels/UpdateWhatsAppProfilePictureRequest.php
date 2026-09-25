<?php

namespace App\Http\Requests\Channels;

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateWhatsAppProfilePictureRequest extends FormRequest
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
            'photo' => [
                'required',
                'file',
                File::image()->types(['jpg', 'jpeg', 'png'])->max('5mb'),
                Rule::dimensions()
                    ->minWidth(192)
                    ->maxWidth(640)
                    ->minHeight(192)
                    ->maxHeight(640)
                    ->ratio(1),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Selecione uma foto para o Perfil do WhatsApp.',
            'photo.image' => 'A foto precisa estar no formato JPEG ou PNG.',
            'photo.mimes' => 'A foto precisa estar no formato JPEG ou PNG.',
            'photo.max' => 'A foto não pode ultrapassar 5 MB.',
            'photo.dimensions' => 'A foto precisa ser quadrada e ter entre 192 e 640 pixels.',
        ];
    }

    public function attributes(): array
    {
        return ['photo' => 'foto'];
    }

    public function photo(): UploadedFile
    {
        return $this->validated('photo');
    }
}
