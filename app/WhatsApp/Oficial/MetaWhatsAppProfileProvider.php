<?php

namespace App\WhatsApp\Oficial;

use App\Exceptions\WhatsAppProfileException;
use App\Models\Channel;
use App\WhatsApp\Profiles\WhatsAppProfileCapabilities;
use App\WhatsApp\Profiles\WhatsAppProfileData;
use App\WhatsApp\Profiles\WhatsAppProfileProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

final class MetaWhatsAppProfileProvider implements WhatsAppProfileProvider
{
    private const PROFILE_FIELDS = 'about,address,description,email,profile_picture_url,websites,vertical';

    private const NUMBER_FIELDS = 'verified_name,name_status,new_display_name,new_name_status';

    public function __construct(private MetaErrorTranslator $errorTranslator) {}

    public function capabilities(): WhatsAppProfileCapabilities
    {
        return new WhatsAppProfileCapabilities(
            readableFields: ['profilePicture', 'displayName', 'about', 'description', 'address', 'email', 'websites', 'category'],
            editableFields: ['profilePicture', 'about', 'description', 'address', 'email', 'websites', 'category'],
            canRequestDisplayName: true,
        );
    }

    public function read(Channel $channel): WhatsAppProfileData
    {
        $phoneNumber = $channel->whatsappPhoneNumber;
        $account = $phoneNumber?->whatsappAccount;

        if ($phoneNumber === null || $account === null) {
            throw new WhatsAppProfileException('O Perfil do WhatsApp não está disponível para este Canal.');
        }

        $baseUrl = rtrim((string) config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version');
        $numberUrl = $baseUrl.'/'.$phoneNumber->phone_number_id;

        try {
            $profile = Http::connectTimeout(3)
                ->timeout(10)
                ->withToken($account->access_token)
                ->get($numberUrl.'/whatsapp_business_profile', ['fields' => self::PROFILE_FIELDS]);

            if (! $profile->successful()) {
                throw new WhatsAppProfileException($this->errorTranslator->translate($profile->json('error')));
            }

            $number = Http::connectTimeout(3)
                ->timeout(10)
                ->withToken($account->access_token)
                ->get($numberUrl, ['fields' => self::NUMBER_FIELDS]);

            if (! $number->successful()) {
                throw new WhatsAppProfileException($this->errorTranslator->translate($number->json('error')));
            }
        } catch (ConnectionException) {
            throw new WhatsAppProfileException('Não foi possível carregar o Perfil do WhatsApp. Verifique sua conexão e tente novamente.');
        }

        $profileData = $profile->json('data.0', []);
        $profileData = is_array($profileData) ? $profileData : [];
        $websites = $profileData['websites'] ?? [];
        $websites = is_array($websites)
            ? array_values(array_filter($websites, 'is_string'))
            : [];

        return new WhatsAppProfileData(
            profilePictureUrl: $profileData['profile_picture_url'] ?? null,
            displayName: $number->json('verified_name'),
            nameStatus: $number->json('name_status'),
            newDisplayName: $number->json('new_display_name'),
            newNameStatus: $number->json('new_name_status'),
            about: $profileData['about'] ?? null,
            description: $profileData['description'] ?? null,
            address: $profileData['address'] ?? null,
            email: $profileData['email'] ?? null,
            websites: $websites,
            category: $profileData['vertical'] ?? null,
        );
    }

    public function updateTexts(Channel $channel, array $values): void
    {
        $phoneNumber = $channel->whatsappPhoneNumber;
        $account = $phoneNumber?->whatsappAccount;

        if ($phoneNumber === null || $account === null) {
            throw new WhatsAppProfileException('O Perfil do WhatsApp não está disponível para este Canal.');
        }

        $fieldMap = [
            'about' => 'about',
            'description' => 'description',
            'address' => 'address',
            'email' => 'email',
            'websites' => 'websites',
            'category' => 'vertical',
        ];
        $payload = ['messaging_product' => 'whatsapp'];

        foreach ($fieldMap as $inputField => $metaField) {
            if (array_key_exists($inputField, $values)) {
                $payload[$metaField] = $values[$inputField] ?? '';
            }
        }

        if (count($payload) === 1) {
            throw new WhatsAppProfileException('Altere pelo menos um campo do Perfil antes de salvar.');
        }

        $url = rtrim((string) config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version').'/'.$phoneNumber->phone_number_id.'/whatsapp_business_profile';

        try {
            $response = Http::connectTimeout(3)
                ->timeout(10)
                ->withToken($account->access_token)
                ->post($url, $payload);

            if (! $response->successful()) {
                throw new WhatsAppProfileException($this->errorTranslator->translate($response->json('error')));
            }
        } catch (ConnectionException) {
            throw new WhatsAppProfileException('Não foi possível salvar o Perfil do WhatsApp. Verifique sua conexão e tente novamente.');
        }
    }

    public function updateProfilePicture(Channel $channel, UploadedFile $photo): void
    {
        $phoneNumber = $channel->whatsappPhoneNumber;
        $account = $phoneNumber?->whatsappAccount;

        if ($phoneNumber === null || $account === null) {
            throw new WhatsAppProfileException('O Perfil do WhatsApp não está disponível para este Canal.');
        }

        if (! filled($account->app_id)) {
            throw new WhatsAppProfileException('Configure o App ID da Meta na aba Conexão para trocar a foto do Perfil do WhatsApp.');
        }

        $baseUrl = rtrim((string) config('services.whatsapp.graph_api_url'), '/').'/'.config('services.whatsapp.graph_api_version');
        $mimeType = $photo->getMimeType() ?: 'image/jpeg';
        $extension = $mimeType === 'image/png' ? 'png' : 'jpg';

        try {
            $session = Http::connectTimeout(3)
                ->timeout(15)
                ->withHeaders(['Authorization' => 'OAuth '.$account->access_token])
                ->post($baseUrl.'/'.rawurlencode((string) $account->app_id).'/uploads?'.http_build_query([
                    'file_length' => $photo->getSize(),
                    'file_type' => $mimeType,
                    'file_name' => 'whatsapp-profile.'.$extension,
                ]));

            if (! $session->successful()) {
                throw new WhatsAppProfileException($this->errorTranslator->translate($session->json('error')));
            }

            $uploadId = $session->json('id');

            if (! is_string($uploadId) || $uploadId === '') {
                throw new WhatsAppProfileException('A Meta não devolveu uma sessão válida para enviar a foto.');
            }

            $upload = Http::connectTimeout(3)
                ->timeout(20)
                ->withHeaders([
                    'Authorization' => 'OAuth '.$account->access_token,
                    'file_offset' => '0',
                    'Content-Type' => $mimeType,
                ])
                ->withBody($photo->getContent(), $mimeType)
                ->post($baseUrl.'/'.$uploadId);

            if (! $upload->successful()) {
                throw new WhatsAppProfileException($this->errorTranslator->translate($upload->json('error')));
            }

            $handle = $upload->json('h');

            if (! is_string($handle) || $handle === '') {
                throw new WhatsAppProfileException('A Meta não devolveu uma referência válida para a foto enviada.');
            }

            $profile = Http::connectTimeout(3)
                ->timeout(15)
                ->withToken($account->access_token)
                ->post($baseUrl.'/'.$phoneNumber->phone_number_id.'/whatsapp_business_profile', [
                    'messaging_product' => 'whatsapp',
                    'profile_picture_handle' => $handle,
                ]);

            if (! $profile->successful()) {
                throw new WhatsAppProfileException($this->errorTranslator->translate($profile->json('error')));
            }
        } catch (ConnectionException) {
            throw new WhatsAppProfileException('Não foi possível trocar a foto do Perfil do WhatsApp. Verifique sua conexão e tente novamente.');
        }
    }
}
