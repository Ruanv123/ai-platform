<?php

namespace App\WhatsApp\Testes;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsAppTestConnection;
use App\WhatsApp\Output\AbstractWhatsAppOutputAdapter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class EvolutionGoOutputAdapter extends AbstractWhatsAppOutputAdapter
{
    public function sendText(Channel $channel, Contact $contact, string $content): Message
    {
        $this->assertContactBelongsToChannel($channel, $contact);
        $connection = $this->connection($channel);
        $response = $this->request($connection)->post(
            $this->endpoint($connection, 'message/sendText'),
            [
                'number' => $contact->phone,
                'text' => $content,
            ],
        )->throw();
        $providerMessageId = $this->providerMessageId($response->json());

        if ($providerMessageId === null) {
            throw new RuntimeException('O WhatsApp de testes não retornou o id da Mensagem enviada.');
        }

        return $this->recordOutgoingText($channel, $contact, $content, $providerMessageId);
    }

    public function sendTemplate(Channel $channel, Contact $contact, string $template, array $parameters = []): Message
    {
        $this->assertContactBelongsToChannel($channel, $contact);
        $connection = $this->connection($channel);
        $response = $this->request($connection)->post(
            $this->endpoint($connection, 'message/sendTemplate'),
            [
                'number' => $contact->phone,
                'name' => $template,
                'parameters' => $parameters,
            ],
        )->throw();
        $providerMessageId = $this->providerMessageId($response->json());
        if ($providerMessageId === null) {
            throw new RuntimeException('O WhatsApp de testes não retornou o id do Template enviado.');
        }

        return $this->recordOutgoingTemplate($channel, $contact, $template, $parameters, $providerMessageId);
    }

    public function markIncomingMessageAsRead(Channel $channel, Message $message): void
    {
        $this->assertIncomingMessageBelongsToChannel($channel, $message);
        $connection = $this->connection($channel);
        $this->request($connection)->post(
            $this->endpoint($connection, 'chat/markMessageAsRead'),
            [
                'readMessages' => [[
                    'remoteJid' => $this->remoteJid($message),
                    'fromMe' => false,
                    'id' => $message->provider_message_id,
                ]],
            ],
        )->throw();
    }

    public function sendTypingIndicator(Channel $channel, Message $message): void
    {
        $this->assertIncomingMessageBelongsToChannel($channel, $message);
        $connection = $this->connection($channel);
        $this->request($connection)->post(
            $this->endpoint($connection, 'chat/sendPresence'),
            [
                'number' => $message->contact->phone,
                'presence' => 'composing',
                'delay' => 5000,
            ],
        )->throw();
    }

    private function request(WhatsAppTestConnection $connection): PendingRequest
    {
        $request = Http::connectTimeout(3)->timeout(10);

        return filled($connection->access_token)
            ? $request->withToken($connection->access_token)
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
        return rtrim($connection->service_url, '/').'/'.trim($path, '/').'/'.rawurlencode($connection->instance_name);
    }

    private function remoteJid(Message $message): string
    {
        $message->loadMissing('contact');

        return $message->contact->phone.'@s.whatsapp.net';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function providerMessageId(array $payload): ?string
    {
        foreach ([
            'key.id',
            'data.key.id',
            'messages.0.id',
            'data.messages.0.id',
            'messageId',
            'data.messageId',
            'id',
            'data.id',
        ] as $path) {
            $value = data_get($payload, $path);

            if (is_scalar($value) && filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
