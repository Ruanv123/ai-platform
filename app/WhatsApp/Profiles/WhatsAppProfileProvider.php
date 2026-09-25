<?php

namespace App\WhatsApp\Profiles;

use App\Models\Channel;

interface WhatsAppProfileProvider
{
    public function capabilities(): WhatsAppProfileCapabilities;

    public function read(Channel $channel): WhatsAppProfileData;

    public function updateTexts(Channel $channel, array $values): void;
}
