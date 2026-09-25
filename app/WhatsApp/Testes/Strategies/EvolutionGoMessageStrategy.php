<?php

namespace App\WhatsApp\Testes\Strategies;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\WhatsApp\Media\IncomingMediaResolver;
use App\WhatsApp\Media\IncomingMediaStorage;
use App\WhatsApp\Testes\EvolutionGoMediaDownloader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

abstract class EvolutionGoMessageStrategy implements EvolutionGoStrategy
{
    /** @var array{contents: string, mime_type: string|null, filename: string}|null */
    private ?array $originalMedia = null;

    private bool $mediaWasExpected = false;

    public function __construct(
        protected readonly IncomingMediaResolver $mediaResolver,
        protected readonly EvolutionGoMediaDownloader $mediaDownloader,
        protected readonly IncomingMediaStorage $mediaStorage,
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

        $content = $this->content($channel, $payload);
        $message = new Message([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => $this->direction($payload),
            'payload_type' => $this->eventType($payload),
            'provider_message_id' => $providerMessageId,
            'content' => $content,
            'delivery_status' => $this->deliveryStatus(),
        ]);
        $timestamp = $this->providerTimestamp($payload);

        if ($timestamp !== null) {
            $message->created_at = $timestamp;
        }

        DB::transaction(function () use ($message): void {
            $message->save();
            $this->persistOriginalMedia($message);
        });

        return $message;
    }

    public function shouldDispatchResponse(): bool
    {
        return true;
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
        $this->mediaWasExpected = true;

        try {
            $media = $this->mediaDownloader->download($channel, $payload, $type);
            $this->originalMedia = $media;

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
    protected function videoContent(Channel $channel, array $payload): string
    {
        $this->mediaWasExpected = true;

        try {
            $this->originalMedia = $this->mediaDownloader->download($channel, $payload, 'video');
        } catch (Throwable) {
            $this->originalMedia = null;
        }

        return $this->textContent($payload) ?? 'O Contato enviou um vídeo.';
    }

    private function persistOriginalMedia(Message $message): void
    {
        if (! $this->mediaWasExpected) {
            return;
        }

        if ($this->originalMedia === null) {
            $this->mediaStorage->markUnavailable($message);

            return;
        }

        try {
            $this->mediaStorage->store($message, $this->originalMedia);
        } catch (Throwable $exception) {
            report($exception);
            $this->mediaStorage->markUnavailable($message);
        }
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
     * Get the whatsmeow message metadata (`data.Info`) of a Message event.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function info(array $payload): array
    {
        $info = data_get($payload, 'data.Info');

        return is_array($info) ? $info : [];
    }

    /**
     * Get the protobuf-shaped message content (`data.Message`).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function messageBody(array $payload): array
    {
        $body = data_get($payload, 'data.Message');

        return is_array($body) ? $body : [];
    }

    /**
     * Get the first content key of the message, ignoring protocol bookkeeping.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function rawMessageType(array $payload): ?string
    {
        foreach (array_keys($this->messageBody($payload)) as $key) {
            if (! in_array($key, ['messageContextInfo', 'senderKeyDistributionMessage'], true)) {
                return $key;
            }
        }

        $type = $this->info($payload)['Type'] ?? null;

        return is_string($type) && filled($type) ? $type : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function detectedMessageType(array $payload): ?string
    {
        $body = $this->messageBody($payload);

        return match (true) {
            array_key_exists('conversation', $body), array_key_exists('extendedTextMessage', $body) => 'text',
            array_key_exists('imageMessage', $body) => 'image',
            array_key_exists('audioMessage', $body) => 'audio',
            array_key_exists('videoMessage', $body) => 'video',
            array_key_exists('documentMessage', $body), array_key_exists('documentWithCaptionMessage', $body) => 'document',
            default => null,
        };
    }

    /**
     * Extract textual content or a media caption without resolving media.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function textContent(array $payload): ?string
    {
        $body = $this->messageBody($payload);
        $content = data_get($body, 'conversation')
            ?? data_get($body, 'extendedTextMessage.text')
            ?? data_get($body, 'imageMessage.caption')
            ?? data_get($body, 'videoMessage.caption')
            ?? data_get($body, 'documentMessage.caption')
            ?? data_get($body, 'documentWithCaptionMessage.message.documentMessage.caption');

        return is_scalar($content) ? (string) $content : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function providerMessageId(array $payload): ?string
    {
        $id = $this->info($payload)['ID'] ?? null;

        return is_string($id) && filled($id) ? $id : null;
    }

    /**
     * Resolve the Contact's phone, since 1:1 chats may be addressed by an opaque `@lid` identifier
     * whose phone JID comes in `SenderAlt` (incoming) or `RecipientAlt` (sent by us).
     *
     * @param  array<string, mixed>  $payload
     */
    protected function phone(array $payload): ?string
    {
        $info = $this->info($payload);
        $alternate = ($info['IsFromMe'] ?? false) === true ? 'RecipientAlt' : 'SenderAlt';
        $jid = collect([$info['Chat'] ?? null, $info[$alternate] ?? null])
            ->first(fn (mixed $candidate): bool => is_string($candidate) && Str::endsWith($candidate, '@s.whatsapp.net'))
            ?? $info['Chat'] ?? null;

        if (! is_string($jid) || blank($jid)) {
            return null;
        }

        $phone = preg_replace('/\\D+/', '', Str::before($jid, '@'));

        return is_string($phone) && filled($phone) ? $phone : null;
    }

    /**
     * Only the Contact's own push name identifies them; on messages we sent it is our own name.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function contactName(array $payload): ?string
    {
        $info = $this->info($payload);
        $name = ($info['IsFromMe'] ?? false) === true ? null : ($info['PushName'] ?? null);

        return is_string($name) && filled($name) ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function providerTimestamp(array $payload): ?Carbon
    {
        $timestamp = $this->info($payload)['Timestamp'] ?? null;

        if (! is_string($timestamp) || blank($timestamp)) {
            return null;
        }

        try {
            return Carbon::parse($timestamp)->setTimezone(config('app.timezone'));
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
