<?php

namespace App\WhatsApp\Testes;

use App\Exceptions\WhatsAppProfileException;
use App\Models\Channel;
use App\WhatsApp\Profiles\WhatsAppProfileCapabilities;
use App\WhatsApp\Profiles\WhatsAppProfileData;
use App\WhatsApp\Profiles\WhatsAppProfileProvider;
use RuntimeException;

final class EvolutionGoWhatsAppProfileProvider implements WhatsAppProfileProvider
{
    public function __construct(private EvolutionGoClient $evolutionGo) {}

    public function capabilities(): WhatsAppProfileCapabilities
    {
        return new WhatsAppProfileCapabilities(
            readableFields: ['profilePicture', 'displayName', 'about'],
            editableFields: ['about'],
            canRequestDisplayName: false,
        );
    }

    public function read(Channel $channel): WhatsAppProfileData
    {
        $connection = $channel->whatsappTestConnection;

        if ($connection === null) {
            throw new WhatsAppProfileException('O Perfil do WhatsApp não está disponível para este Canal.');
        }

        try {
            $displayName = $this->evolutionGo->profileDisplayName($connection->access_token);
            $instanceJid = $this->evolutionGo->profileInstanceJid(
                $connection->instance_name,
                $connection->access_token,
            );

            return new WhatsAppProfileData(
                profilePictureUrl: $this->evolutionGo->profilePictureUrl(
                    $connection->access_token,
                    $instanceJid,
                ),
                displayName: $displayName,
                nameStatus: null,
                newDisplayName: null,
                newNameStatus: null,
                about: $this->evolutionGo->profileAbout($connection->access_token, $instanceJid),
                description: null,
                address: null,
                email: null,
                websites: [],
                category: null,
            );
        } catch (RuntimeException $exception) {
            throw new WhatsAppProfileException($exception->getMessage(), 0, $exception);
        }
    }

    public function updateTexts(Channel $channel, array $values): void
    {
        $connection = $channel->whatsappTestConnection;

        if ($connection === null) {
            throw new WhatsAppProfileException('O Perfil do WhatsApp não está disponível para este Canal.');
        }

        $about = $values['about'] ?? null;

        if (! is_string($about) || trim($about) === '') {
            throw new WhatsAppProfileException('O recado precisa ter pelo menos 1 caractere.');
        }

        try {
            $this->evolutionGo->updateProfileAbout(
                $connection->access_token,
                $about,
            );
        } catch (RuntimeException $exception) {
            throw new WhatsAppProfileException($exception->getMessage(), 0, $exception);
        }
    }
}
