<?php

namespace App\WhatsApp\Testes;

use App\Exceptions\WhatsAppProfileException;
use App\Models\Channel;
use App\WhatsApp\Profiles\WhatsAppProfileCapabilities;
use App\WhatsApp\Profiles\WhatsAppProfileData;
use App\WhatsApp\Profiles\WhatsAppProfileProvider;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

final class EvolutionGoWhatsAppProfileProvider implements WhatsAppProfileProvider
{
    public function __construct(private EvolutionGoClient $evolutionGo) {}

    public function capabilities(): WhatsAppProfileCapabilities
    {
        return new WhatsAppProfileCapabilities(
            readableFields: ['profilePicture', 'displayName', 'about'],
            editableFields: ['profilePicture', 'about'],
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

    public function updateProfilePicture(Channel $channel, UploadedFile $photo): void
    {
        $connection = $channel->whatsappTestConnection;

        if ($connection === null) {
            throw new WhatsAppProfileException('O Perfil do WhatsApp não está disponível para este Canal.');
        }

        $extension = match ($photo->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw new WhatsAppProfileException('A foto precisa estar no formato JPEG ou PNG.'),
        };
        $photoToken = Str::random(64);
        $path = 'whatsapp-profile-photos/'.$photoToken.'.'.$extension;

        try {
            $storedPath = Storage::disk('whatsapp_media')->putFileAs(
                'whatsapp-profile-photos',
                $photo,
                $photoToken.'.'.$extension,
            );

            if (! is_string($storedPath)) {
                throw new WhatsAppProfileException('Não foi possível preparar a foto para o Evolution Go.');
            }

            $imageUrl = URL::temporarySignedRoute(
                'channels.whatsapp-test.profile.photo.serve',
                now()->addMinutes(5),
                [
                    'photoToken' => $photoToken,
                    'extension' => $extension,
                ],
            );

            try {
                $this->evolutionGo->updateProfilePicture($connection->access_token, $imageUrl);
            } catch (RuntimeException $exception) {
                throw new WhatsAppProfileException($exception->getMessage(), 0, $exception);
            }
        } finally {
            Storage::disk('whatsapp_media')->delete($path);
        }
    }

    public function requestDisplayName(Channel $channel, string $displayName): void
    {
        throw new WhatsAppProfileException('O Evolution Go não suporta pedidos de alteração do nome de exibição.');
    }
}
