<?php

namespace App\WhatsApp\Profiles;

final readonly class WhatsAppProfileData
{
    public function __construct(
        public ?string $profilePictureUrl,
        public ?string $displayName,
        public ?string $nameStatus,
        public ?string $newDisplayName,
        public ?string $newNameStatus,
        public ?string $about,
        public ?string $description,
        public ?string $address,
        public ?string $email,
        public array $websites,
        public ?string $category,
    ) {}

    public function toArray(): array
    {
        return [
            'profilePictureUrl' => $this->profilePictureUrl,
            'displayName' => $this->displayName,
            'nameStatus' => $this->nameStatus,
            'newDisplayName' => $this->newDisplayName,
            'newNameStatus' => $this->newNameStatus,
            'about' => $this->about,
            'description' => $this->description,
            'address' => $this->address,
            'email' => $this->email,
            'websites' => $this->websites,
            'category' => $this->category,
        ];
    }
}
