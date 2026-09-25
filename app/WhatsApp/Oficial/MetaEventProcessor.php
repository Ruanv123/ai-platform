<?php

namespace App\WhatsApp\Oficial;

use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\WhatsAppAccount;
use App\WhatsApp\IncomingMessageResponseDispatcher;
use App\WhatsApp\Media\IncomingMediaResolver;
use App\WhatsApp\Media\IncomingMediaStorage;
use App\WhatsApp\MessageReactionUpdater;
use App\WhatsApp\Oficial\Strategies\MetaAudioStrategy;
use App\WhatsApp\Oficial\Strategies\MetaButtonReplyStrategy;
use App\WhatsApp\Oficial\Strategies\MetaDocumentStrategy;
use App\WhatsApp\Oficial\Strategies\MetaImageStrategy;
use App\WhatsApp\Oficial\Strategies\MetaReactionStrategy;
use App\WhatsApp\Oficial\Strategies\MetaStatusStrategy;
use App\WhatsApp\Oficial\Strategies\MetaStrategy;
use App\WhatsApp\Oficial\Strategies\MetaTextStrategy;
use App\WhatsApp\Oficial\Strategies\MetaUnsupportedStrategy;
use App\WhatsApp\Oficial\Strategies\MetaVideoStrategy;
use App\WhatsApp\WhatsAppTemplateService;
use App\WhatsApp\WhatsAppTemplateSynchronizer;

final class MetaEventProcessor
{
    public function __construct(
        private readonly IncomingMediaResolver $mediaResolver,
        private readonly MetaMediaDownloader $mediaDownloader,
        private readonly IncomingMediaStorage $mediaStorage,
        private readonly IncomingMessageResponseDispatcher $responseDispatcher,
        private readonly MessageReactionUpdater $reactionUpdater,
        private readonly MetaAccountHealthProcessor $accountHealthProcessor,
        private readonly WhatsAppTemplateService $templateService,
        private readonly WhatsAppTemplateSynchronizer $templateSynchronizer,
    ) {}

    /**
     * Process a Meta event without translating it to a canonical event.
     *
     * @param  array<string, mixed>  $payload
     */
    public function process(Channel $channel, array $payload): void
    {
        $field = data_get($payload, 'entry.0.changes.0.field');
        $value = data_get($payload, 'entry.0.changes.0.value');

        if ($field === 'messages') {
            $strategy = $this->strategy($payload);
            $message = $strategy->handle($channel, $payload);

            if ($message?->direction === MessageDirection::Incoming && $strategy->shouldDispatchResponse()) {
                $this->responseDispatcher->dispatch($channel, $message);
            }

            return;
        }

        if (is_string($field) && in_array($field, [
            'message_template_status_update',
            'template_category_update',
            'message_template_quality_update',
        ], true)) {
            $channel->loadMissing('whatsappPhoneNumber.whatsappAccount');
            $account = $channel->whatsappPhoneNumber?->whatsappAccount;

            if ($account instanceof WhatsAppAccount && is_array($value)
                && ! $this->templateService->updateFromWebhook($account, $field, $value)) {
                $this->templateSynchronizer->sync($account);
            }

            return;
        }

        if (is_string($field) && is_array($value)) {
            $this->accountHealthProcessor->process($channel, $field, $value);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function strategy(array $payload): MetaStrategy
    {
        if (is_array(data_get($payload, 'entry.0.changes.0.value.statuses.0'))) {
            return new MetaStatusStrategy;
        }

        return match (data_get($payload, 'entry.0.changes.0.value.messages.0.type')) {
            'button' => new MetaButtonReplyStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'interactive' => data_get($payload, 'entry.0.changes.0.value.messages.0.interactive.type') === 'button_reply'
                ? new MetaButtonReplyStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage)
                : new MetaUnsupportedStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'text' => new MetaTextStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'image' => new MetaImageStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'audio' => new MetaAudioStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'video' => new MetaVideoStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'document' => new MetaDocumentStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
            'reaction' => new MetaReactionStrategy($this->reactionUpdater),
            default => new MetaUnsupportedStrategy($this->mediaResolver, $this->mediaDownloader, $this->mediaStorage),
        };
    }
}
