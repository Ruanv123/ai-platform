<?php

namespace App\Notifications\Shopify;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopConnectionErrorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $shopName,
        public string $shopDomain,
        public string $suggestedAction,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reconecte a Loja Shopify '.$this->shopName)
            ->greeting('Olá, '.$notifiable->name.'.')
            ->line('A Shopify recusou ou expirou a autorização de '.$this->shopDomain.'.')
            ->line($this->suggestedAction)
            ->action('Abrir integrações', route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'shop_name' => $this->shopName,
            'shop_domain' => $this->shopDomain,
            'suggested_action' => $this->suggestedAction,
        ];
    }
}
