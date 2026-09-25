<?php

namespace App\WhatsApp\Output;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

abstract class AbstractWhatsAppOutputAdapter implements WhatsAppOutputAdapter
{
    protected function storeOutgoingMedia(UploadedFile $file): array
    {
        $path = $file->store('outgoing', 'whatsapp_media');

        if (! is_string($path)) {
            throw new RuntimeException('Não foi possível salvar o arquivo enviado.');
        }

        return [
            'path' => $path,
            'filename' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => (int) $file->getSize(),
        ];
    }

    protected function postStoredMedia(
        PendingRequest $request,
        string $url,
        array $data,
        array $media,
    ): Response {
        $stream = Storage::disk('whatsapp_media')->readStream($media['path']);

        if (! is_resource($stream)) {
            throw new RuntimeException('Não foi possível ler o arquivo enviado.');
        }

        try {
            return $request
                ->attach('file', $stream, $media['filename'], ['Content-Type' => $media['mime_type']])
                ->post($url, $data)
                ->throw();
        } finally {
            fclose($stream);
        }
    }

    protected function recordOutgoingMedia(
        Channel $channel,
        Contact $contact,
        string $type,
        array $media,
        string $providerMessageId,
    ): Message {
        return Message::create([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Outgoing,
            'payload_type' => $type,
            'provider_message_id' => $providerMessageId,
            'delivery_status' => MessageDeliveryStatus::Sent,
            'media_path' => $media['path'],
            'media_filename' => $media['filename'],
            'media_mime_type' => $media['mime_type'],
            'media_size' => $media['size'],
            'media_available' => true,
        ]);
    }

    protected function deleteStoredMedia(array $media): void
    {
        Storage::disk('whatsapp_media')->delete($media['path']);
    }

    protected function recordOutgoingText(
        Channel $channel,
        Contact $contact,
        string $content,
        string $providerMessageId,
    ): Message {
        return Message::firstOrCreate([
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Outgoing,
            'provider_message_id' => $providerMessageId,
        ], [
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'payload_type' => 'text',
            'content' => $content,
            'delivery_status' => MessageDeliveryStatus::Sent,
        ]);
    }

    /** @param list<string> $parameters */
    protected function recordOutgoingTemplate(
        Channel $channel,
        Contact $contact,
        string|WhatsAppTemplate $template,
        array $parameters,
        string $providerMessageId,
        ?array $media = null,
    ): Message {
        return Message::create([
            'team_id' => $channel->team_id,
            'contact_id' => $contact->getKey(),
            'channel_id' => $channel->getKey(),
            'direction' => MessageDirection::Outgoing,
            'payload_type' => 'template',
            'provider_message_id' => $providerMessageId,
            'content' => json_encode([
                'template' => $template instanceof WhatsAppTemplate ? $template->name : $template,
                'template_id' => $template instanceof WhatsAppTemplate ? $template->getKey() : null,
                'language' => $template instanceof WhatsAppTemplate ? $template->language : null,
                'parameters' => $parameters,
            ], JSON_THROW_ON_ERROR),
            'delivery_status' => MessageDeliveryStatus::Sent,
            ...($media === null ? [] : [
                'media_path' => $media['path'],
                'media_filename' => $media['filename'],
                'media_mime_type' => $media['mime_type'],
                'media_size' => $media['size'],
                'media_available' => true,
            ]),
        ]);
    }

    protected function assertContactBelongsToChannel(Channel $channel, Contact $contact): void
    {
        if ($contact->team_id !== $channel->team_id) {
            throw new InvalidArgumentException('O Contato não pertence à Empresa do Canal.');
        }
    }

    protected function assertIncomingMessageBelongsToChannel(Channel $channel, Message $message): void
    {
        $message->loadMissing('contact');

        if (
            $message->channel_id !== $channel->getKey()
            || $message->contact->team_id !== $channel->team_id
            || $message->direction !== MessageDirection::Incoming
            || blank($message->provider_message_id)
        ) {
            throw new InvalidArgumentException('A Mensagem de entrada não pertence ao Canal ou não possui id do provedor.');
        }
    }
}
