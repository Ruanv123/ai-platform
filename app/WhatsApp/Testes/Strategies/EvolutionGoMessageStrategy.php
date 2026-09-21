<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\WhatsApp\Media\IncomingMediaResolver;
use App\WhatsApp\Testes\EvolutionGoMediaDownloader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

abstract class EvolutionGoMessageStrategy implements EvolutionGoStrategy
{
    public function __construct(
        protected readonly IncomingMediaResolver $mediaResolver,
        protected readonly EvolutionGoMediaDownloader $mediaDownloader,
    ) {}

    /**
     * Process a message-shaped Evolution Go payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): ?Message
    {
        $providerMessageId = $this->providerMessageId($payload);

        if ($this->messageAlreadyExists($channel, $providerMessageId)) {
            return null;
        }

        $phone = $this->phone($payload);

        if ($phone === null) {
            return null;
        }

        $contact = Contact::query()->firstOrNew([
            'team_id' => $channel->team_id,
            'phone' => $phone,
        ]);
        $name = $this->contactName($payload);

        if (filled($name) && $contact->name !== $name) {
            $contact->name = $name;
        }

        $contact->save();

        $message = new Message([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => $this->direction($payload),
            'payload_type' => $this->eventType($payload),
            'provider_message_id' => $providerMessageId,
            'content' => $this->content($channel, $payload),
            'delivery_status' => $this->deliveryStatus(),
        ]);
        $timestamp = $this->providerTimestamp($payload);

        if ($timestamp !== null) {
            $message->created_at = $timestamp;
        }

        $message->save();

        return $message;
    }

    /**
     * Get the event type stored for the message.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function eventType(array $payload): string;

    /**
     * Get the resolved content stored for the message.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function content(Channel $channel, array $payload): ?string;

    /**
     * Download and resolve Evolution Go media when the provider supplied a source.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function mediaContent(
        Channel $channel,
        array $payload,
        string $type,
        ?string $caption,
    ): ?string {
        try {
            $media = $this->mediaDownloader->download($channel, $payload, $type);

            if ($media === null) {
                return $caption;
            }

            return $this->mediaResolver->withCaption(
                $caption,
                $this->mediaResolver->resolve($type, $media),
            );
        } catch (Throwable) {
            return $this->mediaResolver->failure($caption);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function videoContent(array $payload): string
    {
        return $this->mediaResolver->videoWarning(
            $this->textContent($payload),
        );
    }

    /**
     * Get the message direction for the strategy.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function direction(array $payload): MessageDirection
    {
        return MessageDirection::Incoming;
    }

    protected function deliveryStatus(): ?MessageDeliveryStatus
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function eventData(array $payload): array
    {
        $data = $payload['data'] ?? [];

        if (! is_array($data)) {
            return [];
        }

        if (is_array($data['data'] ?? null)) {
            return $data['data'];
        }

        return $data;
    }

    /**
     * Get the provider's message envelope when it wraps the message fields one level deeper.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function messageEnvelope(array $payload): array
    {
        $data = $this->eventData($payload);
        $message = $data['message'] ?? null;

        return is_array($message) && isset($message['key']) && is_array($message['key'])
            ? $message
            : $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function messageBody(array $payload): array
    {
        $data = $this->messageEnvelope($payload);
        $message = $data['message'] ?? [];

        if (! is_array($message)) {
            return [];
        }

        if (isset($message['key']) && is_array($message['message'] ?? null)) {
            return $message['message'];
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function rawMessageType(array $payload): ?string
    {
        $data = $this->messageEnvelope($payload);
        $type = $data['messageType'] ?? $data['type'] ?? null;

        if (is_string($type) && filled($type)) {
            return $type;
        }

        if (is_scalar($data['message'] ?? null)) {
            return 'text';
        }

        foreach (['conversation', 'extendedTextMessage', 'imageMessage', 'audioMessage', 'videoMessage', 'documentMessage'] as $key) {
            if (array_key_exists($key, $this->messageBody($payload))) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function detectedMessageType(array $payload): ?string
    {
        $rawType = Str::lower((string) $this->rawMessageType($payload));
        $body = $this->messageBody($payload);

        if (in_array($rawType, ['conversation', 'extendedtextmessage', 'text', 'textmessage'], true)
            || array_key_exists('conversation', $body)
            || array_key_exists('extendedTextMessage', $body)) {
            return 'text';
        }

        if (in_array($rawType, ['image', 'imagemessage'], true) || array_key_exists('imageMessage', $body)) {
            return 'image';
        }

        if (in_array($rawType, ['audio', 'audiomessage'], true) || array_key_exists('audioMessage', $body)) {
            return 'audio';
        }

        if (in_array($rawType, ['video', 'videomessage'], true) || array_key_exists('videoMessage', $body)) {
            return 'video';
        }

        if (in_array($rawType, ['document', 'documentmessage', 'documentwithcaptionmessage'], true)
            || array_key_exists('documentMessage', $body)) {
            return 'document';
        }

        return null;
    }

    /**
     * Extract textual content or a media caption without resolving media.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function textContent(array $payload): ?string
    {
        $data = $this->messageEnvelope($payload);
        $body = $this->messageBody($payload);
        $content = data_get($body, 'conversation')
            ?? data_get($body, 'extendedTextMessage.text')
            ?? data_get($body, 'imageMessage.caption')
            ?? data_get($body, 'videoMessage.caption')
            ?? data_get($body, 'documentMessage.caption')
            ?? ($data['text'] ?? null)
            ?? ($data['content'] ?? null)
            ?? ($data['caption'] ?? null)
            ?? (is_scalar($data['message'] ?? null) ? $data['message'] : null);

        return is_scalar($content) ? (string) $content : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function providerMessageId(array $payload): ?string
    {
        $data = $this->messageEnvelope($payload);
        $key = is_array($data['key'] ?? null) ? $data['key'] : [];
        $id = $key['id']
            ?? $data['messageId']
            ?? $data['message_id']
            ?? $data['id']
            ?? (is_array($data['message'] ?? null) ? ($data['message']['id'] ?? null) : null)
            ?? null;

        return is_scalar($id) && filled($id) ? (string) $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function phone(array $payload): ?string
    {
        $data = $this->messageEnvelope($payload);
        $key = is_array($data['key'] ?? null) ? $data['key'] : [];
        $rawPhone = $key['remoteJid']
            ?? $key['remoteJidAlt']
            ?? $data['remoteJid']
            ?? $data['phone']
            ?? $data['from']
            ?? $data['sender']
            ?? $data['number']
            ?? $data['senderPhone']
            ?? null;

        if (! is_string($rawPhone) || blank($rawPhone)) {
            return null;
        }

        $phone = Str::before($rawPhone, '@');
        $phone = preg_replace('/\\D+/', '', $phone);

        return is_string($phone) && filled($phone) ? $phone : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function contactName(array $payload): ?string
    {
        $data = $this->messageEnvelope($payload);
        $name = $data['pushName']
            ?? $data['push_name']
            ?? $data['senderName']
            ?? data_get($data, 'profile.name')
            ?? $data['name']
            ?? null;

        return is_scalar($name) && filled($name) ? (string) $name : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function providerTimestamp(array $payload): ?Carbon
    {
        $data = $this->messageEnvelope($payload);
        $timestamp = $data['messageTimestamp'] ?? $data['timestamp'] ?? null;

        if (! is_scalar($timestamp) || blank($timestamp)) {
            return null;
        }

        try {
            if (is_numeric($timestamp)) {
                $seconds = (int) $timestamp;

                if ($seconds > 10_000_000_000) {
                    $seconds = (int) floor($seconds / 1000);
                }

                return Carbon::createFromTimestamp($seconds);
            }

            return Carbon::parse((string) $timestamp);
        } catch (Throwable) {
            return null;
        }
    }

    protected function messageAlreadyExists(Channel $channel, ?string $providerMessageId): bool
    {
        return $providerMessageId !== null
            && Message::query()
                ->where('channel_id', $channel->getKey())
                ->where('provider_message_id', $providerMessageId)
                ->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function rawEventType(array $payload): string
    {
        $event = $payload['event'] ?? 'unsupported';

        return is_scalar($event) && filled($event)
            ? Str::substr((string) $event, 0, 64)
            : 'unsupported';
    }
}
