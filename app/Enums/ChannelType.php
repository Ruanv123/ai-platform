<?php

namespace App\Enums;

enum ChannelType: string
{
    case WhatsApp = 'whatsapp';
    case WebsiteChat = 'website_chat';
    case InstagramDirect = 'instagram_direct';

    /**
     * Get the display label for the channel type.
     */
    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::WebsiteChat => 'Chat no site',
            self::InstagramDirect => 'Instagram Direct',
        };
    }

    /**
     * Get the catalog description for the channel type.
     */
    public function description(): string
    {
        return match ($this) {
            self::WhatsApp => 'Atenda seus clientes pelo número oficial da sua Empresa.',
            self::WebsiteChat => 'Adicione um widget de atendimento ao site da sua Empresa.',
            self::InstagramDirect => 'Receba e responda mensagens enviadas pelo Instagram da sua Empresa.',
        };
    }

    /**
     * Get the Lucide icon name used by the frontend catalog.
     */
    public function icon(): string
    {
        return match ($this) {
            self::WhatsApp => 'message-circle',
            self::WebsiteChat => 'globe-2',
            self::InstagramDirect => 'instagram',
        };
    }

    /**
     * Determine whether this channel can be configured today.
     */
    public function isAvailable(): bool
    {
        return $this === self::WhatsApp;
    }

    /**
     * Get the destination for configuring this channel.
     */
    public function destination(): ?string
    {
        return match ($this) {
            self::WhatsApp => route('channels.whatsapp.edit'),
            self::WebsiteChat, self::InstagramDirect => null,
        };
    }

    /**
     * Get all channel types prepared for the channels catalog.
     *
     * @return array<int, array{value: string, label: string, description: string, icon: string, available: bool, destination: string|null}>
     */
    public static function catalog(): array
    {
        return array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'icon' => $type->icon(),
                'available' => $type->isAvailable(),
                'destination' => $type->destination(),
            ],
            self::cases(),
        );
    }
}
