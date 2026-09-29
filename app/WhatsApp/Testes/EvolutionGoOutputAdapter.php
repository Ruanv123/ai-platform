<?php

namespace App\WhatsApp\Testes;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppTestConnection;
use App\WhatsApp\E164PhoneNumber;
use App\WhatsApp\Output\AbstractWhatsAppOutputAdapter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final class EvolutionGoOutputAdapter extends AbstractWhatsAppOutputAdapter
{
    public function sendText(Channel $channel, Contact $contact, string $content): Message
    {
        $this->assertContactBelongsToChannel($channel, $contact);
        $connection = $this->connection($channel);
        $response = $this->request($connection)->post(
            $this->endpoint($connection, 'send/text'),
            [
                'number' => E164PhoneNumber::recipient($contact->phone),
                'text' => $content,
            ],
        )->throw();
        $providerMessageId = $this->providerMessageId($response->json());

        if ($providerMessageId === null) {
            throw new RuntimeException('O WhatsApp de testes não retornou o id da Mensagem enviada.');
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
            $connection = $this->connection($channel);
            $response = $this->postStoredMedia(
                $this->request($connection),
                $this->endpoint($connection, 'send/media'),
                [
                    'number' => $contact->phone,
                    'type' => $type,
                    'filename' => $media['filename'],
                ],
                $media,
            );
            $providerMessageId = $this->providerMessageId($response->json());

            if ($providerMessageId === null) {
                throw new RuntimeException('O WhatsApp de testes não retornou o id da Mensagem com mídia enviada.');
            }

            return $this->recordOutgoingMedia($channel, $contact, $type, $media, $providerMessageId);
        } catch (\Throwable $exception) {
            $this->deleteStoredMedia($media);

            throw $exception;
        }
    }

    public function sendTemplate(Channel $channel, Contact $contact, string|WhatsAppTemplate $template, array $parameters = []): Message
    {
        $this->assertContactBelongsToChannel($channel, $contact);

        throw new RuntimeException('O Evolution Go não oferece envio de templates oficiais do WhatsApp Business.');
    }

    public function markIncomingMessageAsRead(Channel $channel, Message $message): void
    {
        $this->assertIncomingMessageBelongsToChannel($channel, $message);
        $connection = $this->connection($channel);
        $this->request($connection)->post(
            $this->endpoint($connection, 'message/markread'),
            [
                'number' => E164PhoneNumber::recipient($message->contact->phone),
                'id' => [$message->provider_message_id],
            ],
        )->throw();
    }

    public function sendTypingIndicator(Channel $channel, Message $message): void
    {
        $this->assertIncomingMessageBelongsToChannel($channel, $message);
        $connection = $this->connection($channel);
        $this->request($connection)->post(
            $this->endpoint($connection, 'message/presence'),
            [
                'number' => E164PhoneNumber::recipient($message->contact->phone),
                'state' => 'composing',
                'delay' => 5000,
            ],
        )->throw();
    }

    private function request(WhatsAppTestConnection $connection): PendingRequest
    {
        $request = Http::connectTimeout(3)->timeout(10);

        return filled($connection->access_token)
            ? $request->withHeader('apikey', $connection->access_token)
            : $request;
    }

    private function connection(Channel $channel): WhatsAppTestConnection
    {
        $channel->loadMissing('whatsappTestConnection');
        $connection = $channel->whatsappTestConnection;

        if ($connection === null || blank($connection->service_url) || blank($connection->instance_name)) {
            throw new RuntimeException('A conexão do WhatsApp de testes não está configurada.');
        }

        return $connection;
    }

    private function endpoint(WhatsAppTestConnection $connection, string $path): string
    {
        return rtrim($connection->service_url, '/').'/'.trim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerMessageId(array $payload): ?string
    {
        foreach ([
            'data.Info.ID',
        ] as $path) {
            $value = data_get($payload, $path);

            if (is_scalar($value) && filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
