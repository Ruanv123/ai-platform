<?php

namespace App\WhatsApp\Oficial;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsAppAccount;
use App\WhatsApp\Output\AbstractWhatsAppOutputAdapter;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class MetaOutputAdapter extends AbstractWhatsAppOutputAdapter
{
    public function sendText(Channel $channel, Contact $contact, string $content): Message
    {
        $this->assertContactBelongsToChannel($channel, $contact);
        $phoneNumber = $this->phoneNumber($channel);
        $response = $this->request($channel)->post(
            $this->messagesUrl($phoneNumber),
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $contact->phone,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $content,
                ],
            ],
        )->throw();
        $providerMessageId = $this->providerMessageId($response->json());

        if ($providerMessageId === null) {
            throw new RuntimeException('A Meta não retornou o id da Mensagem enviada.');
        }

        return $this->recordOutgoingText($channel, $contact, $content, $providerMessageId);
    }

    public function markIncomingMessageAsRead(Channel $channel, Message $message): void
    {
        $this->assertIncomingMessageBelongsToChannel($channel, $message);
        $this->request($channel)->post(
            $this->messagesUrl($this->phoneNumber($channel)),
            [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $message->provider_message_id,
            ],
        )->throw();
    }

    public function sendTypingIndicator(Channel $channel, Message $message): void
    {
        $this->assertIncomingMessageBelongsToChannel($channel, $message);
        $this->request($channel)->post(
            $this->messagesUrl($this->phoneNumber($channel)),
            [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $message->provider_message_id,
                'typing_indicator' => [
                    'type' => 'text',
                ],
            ],
        )->throw();
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

    private function messagesUrl(string $phoneNumberId): string
    {
        $graphUrl = rtrim((string) config('services.whatsapp.graph_api_url', 'https://graph.facebook.com'), '/');
        $graphVersion = trim((string) config('services.whatsapp.graph_api_version', 'v23.0'), '/');

        return $graphUrl.'/'.$graphVersion.'/'.rawurlencode($phoneNumberId).'/messages';
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
