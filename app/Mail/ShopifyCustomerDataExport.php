<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ShopifyCustomerDataExport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $shopDomain,
        public string $jsonContent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Dados solicitados para a loja Shopify '.$this->shopDomain,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>O arquivo com os dados disponíveis do cliente está anexado.</p>',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(
                fn (): string => $this->jsonContent,
                'shopify-customer-data.json',
            )->withMime('application/json'),
        ];
    }
}
