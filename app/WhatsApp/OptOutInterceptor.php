<?php

namespace App\WhatsApp;

use App\Consent\ContactConsentService;
use App\Enums\ConsentPurpose;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\WhatsApp\Output\WhatsAppOutputAdapterFactory;
use Illuminate\Support\Str;
use Throwable;

final class OptOutInterceptor
{
    public function __construct(
        private readonly ContactConsentService $consents,
        private readonly WhatsAppOutputAdapterFactory $outputAdapterFactory,
    ) {}

    /**
     * Intercept a keyword before any Agent job is dispatched.
     */
    public function intercept(Channel $channel, Message $message): bool
    {
        $content = trim((string) $message->content);
        if ($content === '' || ! $this->isOptOut($content)) {
            return false;
        }

        $contact = $message->contact()->withoutGlobalScopes()->first();
        if ($contact === null) {
            return false;
        }

        $this->consents->revoke(
            $contact,
            ConsentPurpose::Marketing,
            'keyword',
            ['keyword' => $content, 'message_id' => $message->getKey()],
        );
        $message->forceFill(['is_responded' => true])->save();
        $this->sendConfirmation($channel, $contact);

        return true;
    }

    private function isOptOut(string $content): bool
    {
        $normalized = Str::lower(Str::ascii($content));
        $normalized = trim((string) preg_replace('/[^a-z0-9]+/', ' ', $normalized));
        $keywords = config('whatsapp.opt_out_keywords', ['sair', 'parar', 'cancelar', 'descadastrar']);

        foreach ((array) $keywords as $keyword) {
            $normalizedKeyword = trim((string) preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii((string) $keyword))));
            if ($normalizedKeyword !== '' && ($normalized === $normalizedKeyword || str_contains(' '.$normalized.' ', ' '.$normalizedKeyword.' '))) {
                return true;
            }
        }

        return false;
    }

    private function sendConfirmation(Channel $channel, Contact $contact): void
    {
        $content = (string) config(
            'whatsapp.opt_out_confirmation',
            'Você foi descadastrado dos nossos envios de marketing e não receberá mais esse tipo de mensagem. Para voltar a receber, fale com a nossa equipe.',
        );

        try {
            $this->outputAdapterFactory->make($channel)->sendText($channel, $contact, $content);
        } catch (Throwable $exception) {
            report($exception);
            Message::create([
                'team_id' => $channel->team_id,
                'contact_id' => $contact->getKey(),
                'channel_id' => $channel->getKey(),
                'direction' => MessageDirection::Outgoing,
                'payload_type' => 'text',
                'provider_message_id' => null,
                'content' => $content,
                'delivery_status' => MessageDeliveryStatus::Failed,
            ]);
        }
    }
}
