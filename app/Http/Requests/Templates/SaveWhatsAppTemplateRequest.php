<?php

namespace App\Http\Requests\Templates;

use App\Enums\TeamPermission;
use App\Enums\WhatsAppTemplateCategory;
use App\Enums\WhatsAppTemplateFormat;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTemplateComponent;
use App\WhatsApp\WhatsAppTemplateLibrary;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    protected function prepareForValidation(): void
    {
        if ($this->filled('format')) {
            return;
        }

        $template = $this->route('template');
        $this->merge([
            'format' => $template instanceof WhatsAppTemplate
                ? $template->format->value
                : WhatsAppTemplateFormat::Standard->value,
        ]);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $format = WhatsAppTemplateFormat::tryFrom((string) $this->input('format')) ?? WhatsAppTemplateFormat::Standard;
        $headerType = strtoupper((string) $this->input('header_type', $this->existingHeaderFormat()));
        $existingHeader = $this->existingComponent(null, 'header');
        $requiresHeaderMedia = in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)
            && ($existingHeader === null
                || $existingHeader->format !== strtolower($headerType)
                || blank($existingHeader->media_handle));

        return [
            'format' => ['required', Rule::enum(WhatsAppTemplateFormat::class), $this->formatMatchesCategory()],
            'whatsapp_account_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9]+(?:_[a-z0-9]+)*$/'],
            'language' => ['required', 'string', 'max:16'],
            'requested_category' => ['required', Rule::enum(WhatsAppTemplateCategory::class)],
            'submit' => ['nullable', 'boolean'],
            'library_key' => ['nullable', 'string', Rule::in(app(WhatsAppTemplateLibrary::class)->keys())],
            'body' => [
                $format === WhatsAppTemplateFormat::Authentication ? 'nullable' : 'required',
                'string',
                $format === WhatsAppTemplateFormat::LimitedTimeOffer ? 'max:600' : 'max:1024',
            ],
            'header_type' => ['nullable', 'string', Rule::in($this->headerTypesFor($format))],
            'header_text' => ['nullable', 'string', 'max:60'],
            'header_media' => [
                $requiresHeaderMedia ? 'required' : 'nullable',
                ...$this->mediaRules($headerType),
            ],
            'footer' => [$format === WhatsAppTemplateFormat::Standard ? 'nullable' : 'prohibited', 'string', 'max:60'],
            'buttons' => [
                in_array($format, [WhatsAppTemplateFormat::Coupon, WhatsAppTemplateFormat::LimitedTimeOffer], true) ? 'required' : 'nullable',
                'array',
                $format === WhatsAppTemplateFormat::Standard ? 'max:3' : 'max:2',
            ],
            'buttons.*.type' => ['required_with:buttons', 'string', Rule::in(['QUICK_REPLY', 'URL', 'PHONE_NUMBER', 'COPY_CODE', 'quick_reply', 'url', 'phone_number', 'copy_code'])],
            'buttons.*.text' => ['nullable', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'string', 'max:2000'],
            'buttons.*.phone_number' => ['nullable', 'string', 'max:32'],
            'buttons.*.example' => ['nullable', 'string', 'max:255'],
            'variables' => ['nullable', 'array'],
            'variables.*.position' => ['required_with:variables', 'integer', 'min:1', 'max:10'],
            'variables.*.component' => ['required_with:variables', 'string', Rule::in(['body', 'header'])],
            'variables.*.example' => ['nullable', 'string', 'max:255'],
            'variables.*.description' => ['nullable', 'string', 'max:255'],
            'offer_text' => [$format === WhatsAppTemplateFormat::LimitedTimeOffer ? 'required' : 'prohibited', 'string', 'max:16'],
            'offer_has_expiration' => ['nullable', 'boolean'],
            'card_header_format' => [$format === WhatsAppTemplateFormat::Carousel ? 'required' : 'prohibited', Rule::in(['IMAGE', 'VIDEO'])],
            'cards' => [$format === WhatsAppTemplateFormat::Carousel ? 'required' : 'prohibited', 'array', 'min:2', 'max:10'],
            'cards.*.body' => ['nullable', 'string', 'max:160'],
            'cards.*.body_examples' => ['nullable', 'array', 'max:10'],
            'cards.*.body_examples.*' => ['nullable', 'string', 'max:255'],
            'cards.*.media' => $this->cardMediaRules(),
            'cards.*.existing_index' => ['nullable', 'integer', 'min:0', 'max:9'],
            'cards.*.buttons' => ['required_with:cards', 'array', 'min:1', 'max:2'],
            'cards.*.buttons.*.type' => ['required', 'string', Rule::in(['quick_reply', 'url', 'phone_number'])],
            'cards.*.buttons.*.text' => ['required', 'string', 'max:25'],
            'cards.*.buttons.*.url' => ['nullable', 'required_if:cards.*.buttons.*.type,url', 'string', 'max:2000'],
            'cards.*.buttons.*.phone_number' => ['nullable', 'required_if:cards.*.buttons.*.type,phone_number', 'string', 'max:32'],
            'cards.*.buttons.*.example' => ['nullable', 'string', 'max:255'],
            'otp_type' => [$format === WhatsAppTemplateFormat::Authentication ? 'required' : 'prohibited', Rule::in(['copy_code', 'one_tap', 'zero_tap'])],
            'package_name' => ['nullable', 'required_if:otp_type,one_tap,zero_tap', 'string', 'max:224'],
            'signature_hash' => ['nullable', 'required_if:otp_type,one_tap,zero_tap', 'string', 'size:11'],
            'add_security_recommendation' => ['nullable', 'boolean'],
            'code_expiration_minutes' => ['nullable', 'integer', 'min:1', 'max:90'],
        ];
    }

    /** @return array<int, Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('format') !== WhatsAppTemplateFormat::Carousel->value || ! is_array($this->input('cards'))) {
                    return;
                }

                $cardFormat = strtolower((string) $this->input('card_header_format'));
                foreach ($this->input('cards') as $cardIndex => $card) {
                    if ($this->hasFile('cards.'.$cardIndex.'.media')) {
                        continue;
                    }

                    $existingIndex = is_array($card) && is_numeric($card['existing_index'] ?? null)
                        ? (int) $card['existing_index']
                        : null;
                    $existing = $existingIndex === null ? null : $this->existingComponent($existingIndex, 'header');
                    if ($existing === null || $existing->format !== $cardFormat || blank($existing->media_handle)) {
                        $validator->errors()->add('cards.'.$cardIndex.'.media', 'Envie a mídia de cada card.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        $mediaType = strtoupper((string) $this->input('header_type', $this->existingHeaderFormat()));
        $mediaMessage = match ($mediaType) {
            'IMAGE' => 'A imagem de exemplo deve estar no formato JPEG ou PNG.',
            'VIDEO' => 'O vídeo de exemplo deve estar no formato MP4 ou 3GP.',
            default => 'O documento de exemplo deve estar no formato PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT.',
        };

        return [
            'name.regex' => 'O nome precisa usar apenas letras minúsculas, números e sublinhados.',
            'body.required' => 'Escreva o corpo do Template.',
            'whatsapp_account_id.required' => 'Escolha uma Conta do WhatsApp oficial.',
            'header_media.required' => 'Envie um arquivo de exemplo para o cabeçalho.',
            'header_media.file' => 'Selecione um arquivo de exemplo válido.',
            'header_media.mimes' => $mediaMessage,
            'header_media.extensions' => $mediaMessage,
            'header_media.max' => match ($mediaType) {
                'IMAGE' => 'A imagem de exemplo deve ter no máximo 5 MB.',
                'VIDEO' => 'O vídeo de exemplo deve ter no máximo 16 MB.',
                default => 'O documento de exemplo deve ter no máximo 100 MB.',
            },
            'header_media.prohibited' => 'O cabeçalho selecionado não aceita arquivo de exemplo.',
            'offer_text.required' => 'Informe o título da oferta.',
            'cards.required' => 'Adicione os cards do carrossel.',
            'cards.min' => 'O carrossel precisa de pelo menos 2 cards.',
            'cards.max' => 'O carrossel pode ter no máximo 10 cards.',
            'cards.*.media.mimes' => 'A mídia do card deve ser JPEG ou PNG para imagem, ou MP4 ou 3GP para vídeo.',
            'cards.*.media.extensions' => 'A mídia do card deve ser JPEG ou PNG para imagem, ou MP4 ou 3GP para vídeo.',
            'cards.*.media.max' => 'A mídia do card deve ter no máximo 5 MB para imagem ou 16 MB para vídeo.',
            'cards.*.buttons.required_with' => 'Cada card precisa de pelo menos um botão.',
            'cards.*.buttons.*.text.required' => 'Informe o texto dos botões dos cards.',
            'otp_type.required' => 'Escolha como o Contato vai usar o código.',
            'package_name.required_if' => 'Informe o pacote do app Android.',
            'signature_hash.required_if' => 'Informe o hash da assinatura do app Android.',
            'signature_hash.size' => 'O hash da assinatura do app tem 11 caracteres.',
        ];
    }

    /** @return array<int, string> */
    private function headerTypesFor(WhatsAppTemplateFormat $format): array
    {
        return match ($format) {
            WhatsAppTemplateFormat::Standard => ['NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'],
            WhatsAppTemplateFormat::Coupon => ['NONE', 'TEXT'],
            WhatsAppTemplateFormat::LimitedTimeOffer => ['NONE', 'IMAGE', 'VIDEO'],
            default => ['NONE'],
        };
    }

    /** @return array<int, string> */
    private function mediaRules(string $mediaType): array
    {
        return match ($mediaType) {
            'IMAGE' => ['file', 'mimes:jpg,jpeg,png', 'extensions:jpg,jpeg,png', 'max:5120'],
            'VIDEO' => ['file', 'mimes:mp4,3gp', 'extensions:mp4,3gp', 'max:16384'],
            'DOCUMENT' => [
                'file',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
                'extensions:pdf,doc,docx,xls,xlsx,ppt,pptx,txt',
                'max:102400',
            ],
            default => ['prohibited'],
        };
    }

    /** @return array<int, string> */
    private function cardMediaRules(): array
    {
        $cardFormat = strtoupper((string) $this->input('card_header_format'));

        return ['nullable', ...$this->mediaRules($cardFormat === 'VIDEO' ? 'VIDEO' : 'IMAGE')];
    }

    private function formatMatchesCategory(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $format = WhatsAppTemplateFormat::tryFrom((string) $value);
            $category = WhatsAppTemplateCategory::tryFrom((string) $this->input('requested_category'));
            if ($format !== null && $category !== null && ! $format->allowsCategory($category)) {
                $fail('O formato escolhido não está disponível para esta categoria.');
            }
        };
    }

    private function existingComponent(?int $cardIndex, string $type): ?WhatsAppTemplateComponent
    {
        $template = $this->route('template');

        return $template instanceof WhatsAppTemplate
            ? $template->components()
                ->where('type', $type)
                ->when($cardIndex === null, fn ($query) => $query->whereNull('card_index'))
                ->when($cardIndex !== null, fn ($query) => $query->where('card_index', $cardIndex))
                ->first()
            : null;
    }

    private function existingHeaderFormat(): string
    {
        return strtoupper((string) ($this->existingComponent(null, 'header')?->format ?? 'TEXT'));
    }

    private function belongsToTeam(WhatsAppTemplate $template): bool
    {
        $teamId = $this->user()?->company()?->getKey();

        return $teamId !== null && $template->account()
            ->where('team_id', $teamId)
            ->exists();
    }
}
