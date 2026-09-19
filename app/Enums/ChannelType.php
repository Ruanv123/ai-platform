<?php

namespace App\Enums;

use App\Models\User;

enum ChannelType: string
{
    case WhatsApp = 'whatsapp';
    case WebsiteChat = 'website_chat';
    case InstagramDirect = 'instagram_direct';
    case WhatsAppTest = 'whatsapp_test';

    /**
     * Get the display label for the channel type.
     */
    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::WhatsAppTest => 'WhatsApp de testes',
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
            self::WhatsAppTest => 'Conecte um número por um serviço não oficial para desenvolver o atendimento.',
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
            self::WhatsAppTest => 'flask-conical',
            self::WebsiteChat => 'globe-2',
            self::InstagramDirect => 'instagram',
        };
    }

    /**
     * Determine whether this channel can be configured today.
     */
    public function isAvailable(): bool
    {
        return in_array($this, [self::WhatsApp, self::WhatsAppTest], true);
    }

    /**
     * Determine whether this channel type should be visible to the user.
     */
    public function isVisible(?User $user = null): bool
    {
        return $this !== self::WhatsAppTest
            || app()->isLocal()
            || (bool) ($user?->is_platform_admin);
    }

    /**
     * Get the destination for configuring this channel.
     */
    public function destination(): ?string
    {
        return match ($this) {
            self::WhatsApp => route('channels.whatsapp.edit'),
            self::WhatsAppTest => route('channels.whatsapp-test.edit'),
            self::WebsiteChat, self::InstagramDirect => null,
        };
    }

    /**
     * Get all visible channel types prepared for the channels catalog.
     *
     * @return array<int, array{value: string, label: string, description: string, icon: string, available: bool, destination: string|null}>
     */
    public static function catalog(?User $user = null): array
    {
        return array_values(array_map(
            fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'icon' => $type->icon(),
                'available' => $type->isAvailable(),
                'destination' => $type->destination(),
            ],
            array_filter(self::cases(), fn (self $type): bool => $type->isVisible($user)),
        ));
    }
}
