<?php

namespace App\WhatsApp\Oficial;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\E164PhoneNumber;
use App\WhatsApp\Output\AbstractWhatsAppOutputAdapter;
use App\WhatsApp\Output\WhatsAppTemplateMediaOutputAdapter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final class MetaOutputAdapter extends AbstractWhatsAppOutputAdapter implements WhatsAppTemplateMediaOutputAdapter
{
    public function __construct(private readonly MetaGraphApiResponseHandler $responseHandler) {}

    public function sendText(Channel $channel, Contact $contact, string $content): Message
    {
        $this->assertContactBelongsToChannel($channel, $contact);
        $phoneNumber = $this->phoneNumber($channel);
        $response = $this->request($channel)->post(
            $this->messagesUrl($phoneNumber),
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => E164PhoneNumber::recipient($contact->phone),
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $content,
                ],
            ],
        );
        $this->responseHandler->markChannelUnavailableForInvalidToken($response, $channel);
        $response->throw();
        $providerMessageId = $this->providerMessageId($response->json());

        if ($providerMessageId === null) {
            throw new RuntimeException('A Meta não retornou o id da Mensagem enviada.');
        }

        return $this->recordOutgoingText($channel, $contact, $content, $providerMessageId);
    }

    public function sendMedia(Channel $channel, Contact $contact, UploadedFile $file, string $type): Message
    {
        $this->assertContactBelongsToChannel($channel, $contact);

        if (! in_array($type, ['image', 'document', 'video', 'audio'], true)) {
            throw new InvalidArgumentException('Tipo de mídia não suportado.');
        }

        $media = $this->storeOutgoingMedia($file);

        try {
            $phoneNumber = $this->phoneNumber($channel);
            $uploadedMedia = $this->postStoredMedia(
                $this->request($channel),
                $this->mediaUrl($phoneNumber),
                ['messaging_product' => 'whatsapp'],
                $media,
                fn (Response $response): mixed => $this->responseHandler->markChannelUnavailableForInvalidToken($response, $channel),
            );
            $mediaId = $uploadedMedia->json('id');

            if (! is_string($mediaId) || blank($mediaId)) {
                throw new RuntimeException('A Meta não retornou o id da mídia enviada.');
            }

            $mediaContent = ['id' => $mediaId];
            if ($type === 'document') {
                $mediaContent['filename'] = $media->filename;
            }
            if ($type === 'audio') {
                $mediaContent['voice'] = $this->isOggOpus($media->mimeType, $media->filename);
            }

            $response = $this->request($channel)->post(
                $this->messagesUrl($phoneNumber),
                [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => E164PhoneNumber::recipient($contact->phone),
                    'type' => $type,
                    $type => $mediaContent,
                ],
            );
            $this->responseHandler->markChannelUnavailableForInvalidToken($response, $channel);
            $response->throw();
            $providerMessageId = $this->providerMessageId($response->json());

            if ($providerMessageId === null) {
                throw new RuntimeException('A Meta não retornou o id da Mensagem com mídia enviada.');
            }

            return $this->recordOutgoingMedia($channel, $contact, $type, $media, $providerMessageId);
        } catch (\Throwable $exception) {
            $this->deleteStoredMedia($media);

            throw $exception;
        }
    }

    public function sendTemplate(Channel $channel, Contact $contact, string|WhatsAppTemplate $template, array $parameters = []): Message
    {
        return $this->sendTemplateMessage($channel, $contact, $template, $parameters, null);
    }

    public function sendTemplateWithMedia(
        Channel $channel,
        Contact $contact,
        string|WhatsAppTemplate $template,
        array $parameters,
        UploadedFile $media,
    ): Message {
        return $this->sendTemplateMessage($channel, $contact, $template, $parameters, $media);
    }

    private function sendTemplateMessage(
        Channel $channel,
        Contact $contact,
        string|WhatsAppTemplate $template,
        array $parameters,
        ?UploadedFile $headerMedia,
    ): Message {
        $this->assertContactBelongsToChannel($channel, $contact);
        $account = $this->account($channel);
        $normalizedTemplate = $template instanceof WhatsAppTemplate
            ? $template
            : $account->templates()
                ->where('name', $template)
                ->where('language', 'pt_BR')
                ->with('components.variables', 'components.buttons')
                ->first();
        $header = $normalizedTemplate?->componentByType('header');
        $mediaType = strtolower((string) $header?->format);
        if ($headerMedia !== null && ! in_array($mediaType, ['image', 'document'], true)) {
            throw new InvalidArgumentException('Escolha um Template aprovado com cabeçalho de imagem ou documento.');
        }
        if ($headerMedia === null && in_array($mediaType, ['image', 'document'], true)) {
            throw new InvalidArgumentException('Selecione o arquivo que será enviado no cabeçalho do Template.');
        }
        if ($headerMedia === null && $mediaType === 'video') {
            throw new InvalidArgumentException('O envio de Templates com cabeçalho de vídeo ainda não está disponível.');
        }

        $storedMedia = $headerMedia === null ? null : $this->storeOutgoingMedia($headerMedia);
        $components = [];
        $definitions = $normalizedTemplate?->variableDefinitions() ?? [];
        if ($definitions !== []) {
            $grouped = [];
            foreach ($definitions as $index => $definition) {
                $component = (string) ($definition['component'] ?? 'body');
                if ($component === 'button') {
                    $components[] = [
                        'type' => 'button',
                        'sub_type' => 'url',
                        'index' => (string) ($definition['buttonIndex'] ?? 0),
                        'parameters' => [[
                            'type' => 'text',
                            'text' => (string) ($parameters[$index] ?? ''),
                        ]],
                    ];

                    continue;
                }

                $grouped[$component][] = [
                    'type' => 'text',
                    'text' => (string) ($parameters[$index] ?? ''),
                ];
            }
            foreach ($grouped as $component => $values) {
                $components[] = ['type' => $component, 'parameters' => $values];
            }
        } elseif ($parameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn (string $parameter): array => ['type' => 'text', 'text' => $parameter], $parameters),
            ];
        }
        try {
            if ($storedMedia !== null) {
                $phoneNumber = $this->phoneNumber($channel);
                $uploadedMedia = $this->postStoredMedia(
                    $this->request($channel),
                    $this->mediaUrl($phoneNumber),
                    ['messaging_product' => 'whatsapp'],
                    $storedMedia,
                    fn (Response $response): mixed => $this->responseHandler->markChannelUnavailableForInvalidToken($response, $channel),
                );
                $mediaId = $uploadedMedia->json('id');
                if (! is_string($mediaId) || blank($mediaId)) {
                    throw new RuntimeException('A Meta não retornou o id do arquivo do Template.');
                }

                $mediaParameter = ['type' => $mediaType, $mediaType => ['id' => $mediaId]];
                if ($mediaType === 'document') {
                    $mediaParameter['document']['filename'] = $storedMedia->filename;
                }
                array_unshift($components, ['type' => 'header', 'parameters' => [$mediaParameter]]);
            }

            $response = $this->request($channel)->post(
                $this->messagesUrl($this->phoneNumber($channel)),
                [
                    'messaging_product' => 'whatsapp',
                    'to' => E164PhoneNumber::recipient($contact->phone),
                    'type' => 'template',
                    'template' => [
                        'name' => $normalizedTemplate?->name ?? ($template instanceof WhatsAppTemplate ? $template->name : $template),
                        'language' => ['code' => $normalizedTemplate?->language ?? 'pt_BR'],
                        'components' => $components,
                    ],
                ],
            );
            $this->responseHandler->markChannelUnavailableForInvalidToken($response, $channel);
            $response->throw();
            $providerMessageId = $this->providerMessageId($response->json());
            if ($providerMessageId === null) {
                throw new RuntimeException('A Meta não retornou o id do Template enviado.');
            }

            return $this->recordOutgoingTemplate(
                $channel,
                $contact,
                $normalizedTemplate ?? $template,
                $parameters,
                $providerMessageId,
                $storedMedia,
            );
        } catch (\Throwable $exception) {
            if ($storedMedia !== null) {
                $this->deleteStoredMedia($storedMedia);
            }

            throw $exception;
        }
    }

    public function markIncomingMessageAsRead(Channel $channel, Message $message): void
    {
        $this->assertIncomingMessageBelongsToChannel($channel, $message);
        $response = $this->request($channel)->post(
            $this->messagesUrl($this->phoneNumber($channel)),
            [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $message->provider_message_id,
            ],
        );
        $this->responseHandler->markChannelUnavailableForInvalidToken($response, $channel);
        $response->throw();
    }

    public function sendTypingIndicator(Channel $channel, Message $message): void
    {
        $this->assertIncomingMessageBelongsToChannel($channel, $message);
        $response = $this->request($channel)->post(
            $this->messagesUrl($this->phoneNumber($channel)),
            [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $message->provider_message_id,
                'typing_indicator' => [
                    'type' => 'text',
                ],
            ],
        );
        $this->responseHandler->markChannelUnavailableForInvalidToken($response, $channel);
        $response->throw();
    }

    private function request(Channel $channel): PendingRequest
    {
        $account = $this->account($channel);

        return Http::withToken($account->access_token)
            ->connectTimeout(3)
            ->timeout(10);
    }

    private function phoneNumber(Channel $channel): string
    {
        $channel->loadMissing('whatsappPhoneNumber');
        $phoneNumber = $channel->whatsappPhoneNumber;

        if ($phoneNumber === null || blank($phoneNumber->phone_number_id)) {
            throw new RuntimeException('O número do WhatsApp oficial não está configurado.');
        }

        return $phoneNumber->phone_number_id;
    }

    private function account(Channel $channel): WhatsAppAccount
    {
        $channel->loadMissing('whatsappPhoneNumber.whatsappAccount');
        $account = $channel->whatsappPhoneNumber?->whatsappAccount;

        if ($account === null) {
            throw new RuntimeException('A conta oficial do WhatsApp não está configurada.');
        }

        return $account;
    }

    private function isOggOpus(string $mimeType, string $filename): bool
    {
        return in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), ['ogg', 'opus'], true)
            && in_array(strtolower($mimeType), ['audio/ogg', 'application/ogg', 'audio/opus'], true);
    }

    private function messagesUrl(string $phoneNumberId): string
    {
        $graphUrl = rtrim((string) config('services.whatsapp.graph_api_url', 'https://graph.facebook.com'), '/');
        $graphVersion = trim((string) config('services.whatsapp.graph_api_version', 'v23.0'), '/');

        return $graphUrl.'/'.$graphVersion.'/'.rawurlencode($phoneNumberId).'/messages';
    }

    private function mediaUrl(string $phoneNumberId): string
    {
        $graphUrl = rtrim((string) config('services.whatsapp.graph_api_url', 'https://graph.facebook.com'), '/');
        $graphVersion = trim((string) config('services.whatsapp.graph_api_version', 'v23.0'), '/');

        return $graphUrl.'/'.$graphVersion.'/'.rawurlencode($phoneNumberId).'/media';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerMessageId(array $payload): ?string
    {
        $value = data_get($payload, 'messages.0.id');

        return is_scalar($value) && filled($value) ? (string) $value : null;
    }
}
