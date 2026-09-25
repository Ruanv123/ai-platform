<?php

namespace App\WhatsApp\Oficial;

use App\Exceptions\WhatsAppProfileException;
use App\Models\Channel;
use App\WhatsApp\Profiles\WhatsAppProfileCapabilities;
use App\WhatsApp\Profiles\WhatsAppProfileData;
use App\WhatsApp\Profiles\WhatsAppProfileProvider;
use Illuminate\Http\Client\ConnectionException;
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
}
