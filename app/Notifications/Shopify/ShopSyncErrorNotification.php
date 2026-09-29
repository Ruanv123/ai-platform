<?php

namespace App\Notifications\Shopify;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShopSyncErrorNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $shopName,
        public string $shopDomain,
        public string $error,
        public string $suggestedAction,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Erro na sincronização Shopify de '.$this->shopName)
            ->greeting('Olá, '.$notifiable->name.'.')
            ->line('A sincronização de '.$this->shopDomain.' encontrou um erro.')
            ->line($this->error)
            ->line($this->suggestedAction)
            ->action('Abrir integrações', route('integrations.show', ['type' => 'ecommerce', 'provider' => 'shopify']));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'shop_name' => $this->shopName,
            'shop_domain' => $this->shopDomain,
            'error' => $this->error,
            'suggested_action' => $this->suggestedAction,
        ];
    }
}
