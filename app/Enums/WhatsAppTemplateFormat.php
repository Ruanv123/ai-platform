<?php

namespace App\Enums;

enum WhatsAppTemplateFormat: string
{
    case Standard = 'standard';
    case Carousel = 'carousel';
    case Coupon = 'coupon';
    case LimitedTimeOffer = 'limited_time_offer';
    case CallPermission = 'call_permission';
    case Authentication = 'authentication';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Personalizado',
            self::Carousel => 'Carrossel de mídia',
            self::Coupon => 'Cupom de desconto',
            self::LimitedTimeOffer => 'Oferta por tempo limitado',
            self::CallPermission => 'Pedido de ligação',
            self::Authentication => 'Código de verificação',
        };
    }

    public function allowsCategory(WhatsAppTemplateCategory $category): bool
    {
        return match ($this) {
            self::Standard => $category !== WhatsAppTemplateCategory::Authentication,
            self::CallPermission => $category !== WhatsAppTemplateCategory::Authentication,
            self::Carousel, self::Coupon, self::LimitedTimeOffer => $category === WhatsAppTemplateCategory::Marketing,
            self::Authentication => $category === WhatsAppTemplateCategory::Authentication,
        };
    }

    public function canSendFromConversation(): bool
    {
        return $this === self::Standard;
    }
}
