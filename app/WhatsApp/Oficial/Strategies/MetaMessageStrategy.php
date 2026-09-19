<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\WhatsApp\Media\IncomingMediaResolver;
use App\WhatsApp\Oficial\MetaMediaDownloader;
use Illuminate\Support\Carbon;
use Throwable;

abstract class MetaMessageStrategy implements MetaStrategy
{
    public function __construct(
        protected readonly IncomingMediaResolver $mediaResolver,
        protected readonly MetaMediaDownloader $mediaDownloader,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): void
    {
        $messageData = $this->message($payload);
        $providerMessageId = $this->stringValue($messageData, 'id');

        if ($this->messageAlreadyExists($channel, $providerMessageId)) {
            return;
        }

        $phone = $this->stringValue($messageData, 'from');

        if ($phone === null) {
            return;
        }

        $contact = Contact::query()->firstOrNew([
            'team_id' => $channel->team_id,
            'phone' => $phone,
        ]);
        $contactName = $this->contactName($payload, $phone);

        if ($contactName !== null && $contact->name !== $contactName) {
            $contact->name = $contactName;
        }

        $contact->save();

        $message = new Message([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Incoming,
            'event_type' => $this->eventType($payload),
            'provider_message_id' => $providerMessageId,
            'content' => $this->content($channel, $payload),
        ]);
        $timestamp = $this->providerTimestamp($messageData);

        if ($timestamp !== null) {
            $message->created_at = $timestamp;
        }

        $message->save();
    }

    /** @param array<string, mixed> $payload */
    abstract protected function eventType(array $payload): string;

    /**
     * Resolve the content stored for this message.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function content(Channel $channel, array $payload): ?string;

    /**
     * Download and resolve a Meta media attachment when one is present.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function mediaContent(
        Channel $channel,
        array $payload,
        string $type,
        ?string $caption,
    ): ?string {
        $media = $this->message($payload)[$type] ?? null;

        if (! is_array($media)) {
            return $caption;
        }

        $mediaId = $this->stringValue($media, 'id');

        if ($mediaId === null) {
            return $caption;
        }

        try {
            $downloaded = $this->mediaDownloader->download(
                $channel,
                $mediaId,
                $this->stringValue($media, 'filename'),
                $this->stringValue($media, 'mime_type'),
            );

            return $this->mediaResolver->withCaption(
                $caption,
                $this->mediaResolver->resolve($type, $downloaded),
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
            $this->stringValue($this->message($payload), 'video.caption'),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function value(array $payload): array
    {
        $value = data_get($payload, 'entry.0.changes.0.value');

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function message(array $payload): array
    {
        $message = data_get($this->value($payload), 'messages.0');

        return is_array($message) ? $message : [];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    protected function stringValue(array $values, string $key): ?string
    {
        $value = data_get($values, $key);

        return is_scalar($value) && filled($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function contactName(array $payload, string $phone): ?string
    {
        $contacts = $this->value($payload)['contacts'] ?? [];

        if (! is_array($contacts)) {
            return null;
        }

        foreach ($contacts as $contact) {
            if (! is_array($contact) || ($contact['wa_id'] ?? null) !== $phone) {
                continue;
            }

            return $this->stringValue($contact, 'profile.name');
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function providerTimestamp(array $message): ?Carbon
    {
        $timestamp = $this->stringValue($message, 'timestamp');

        if ($timestamp === null) {
            return null;
        }

        try {
            return is_numeric($timestamp)
                ? Carbon::createFromTimestamp((int) $timestamp)
                : Carbon::parse($timestamp);
        } catch (Throwable) {
            return null;
        }
    }

    private function messageAlreadyExists(Channel $channel, ?string $providerMessageId): bool
    {
        return $providerMessageId !== null
            && Message::query()
                ->where('channel_id', $channel->getKey())
                ->where('provider_message_id', $providerMessageId)
                ->exists();
    }
}
