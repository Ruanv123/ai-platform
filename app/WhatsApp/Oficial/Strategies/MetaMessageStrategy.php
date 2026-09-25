<?php

namespace App\WhatsApp\Oficial\Strategies;

use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\WhatsApp\Media\IncomingMediaResolver;
use App\WhatsApp\Media\IncomingMediaStorage;
use App\WhatsApp\Oficial\MetaMediaDownloader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

abstract class MetaMessageStrategy implements MetaStrategy
{
    /** @var array{contents: string, mime_type: string|null, filename: string}|null */
    private ?array $originalMedia = null;

    private bool $mediaWasExpected = false;

    public function __construct(
        protected readonly IncomingMediaResolver $mediaResolver,
        protected readonly MetaMediaDownloader $mediaDownloader,
        protected readonly IncomingMediaStorage $mediaStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(Channel $channel, array $payload): ?Message
    {
        $messageData = $this->message($payload);
        $providerMessageId = $this->stringValue($messageData, 'id');

        if ($this->messageAlreadyExists($channel, $providerMessageId)) {
            return null;
        }

        $phone = $this->stringValue($messageData, 'from');

        if ($phone === null) {
            return null;
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

        $content = $this->content($channel, $payload);
        $message = new Message([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Incoming,
            'payload_type' => $this->eventType($payload),
            'provider_message_id' => $providerMessageId,
            'content' => $content,
        ]);
        $timestamp = $this->providerTimestamp($messageData);

        if ($timestamp !== null) {
            $message->created_at = $timestamp;
        }

        DB::transaction(function () use ($message): void {
            $message->save();
            $this->persistOriginalMedia($message);
        });

        return $message;
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
        $this->mediaWasExpected = true;
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
            $this->originalMedia = $downloaded;

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
    protected function videoContent(Channel $channel, array $payload): string
    {
        $this->mediaWasExpected = true;

        try {
            $media = $this->message($payload)['video'] ?? null;
            $mediaId = is_array($media) ? $this->stringValue($media, 'id') : null;

            if ($mediaId !== null) {
                $this->originalMedia = $this->mediaDownloader->download(
                    $channel,
                    $mediaId,
                    $this->stringValue($media, 'filename'),
                    $this->stringValue($media, 'mime_type'),
                );
            }
        } catch (Throwable) {
            $this->originalMedia = null;
        }

        return $this->mediaResolver->videoWarning(
            $this->stringValue($this->message($payload), 'video.caption'),
        );
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
            $providerTimestamp = is_numeric($timestamp)
                ? Carbon::createFromTimestamp((int) $timestamp)
                : Carbon::parse($timestamp);

            return $providerTimestamp->setTimezone(config('app.timezone'));
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
