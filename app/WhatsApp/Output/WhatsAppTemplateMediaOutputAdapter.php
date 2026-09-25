<?php

namespace App\WhatsApp\Output;

use App\Models\Channel;
use App\Models\Contact;
use App\Models\Message;
use App\Models\WhatsAppTemplate;
use Illuminate\Http\UploadedFile;

interface WhatsAppTemplateMediaOutputAdapter extends WhatsAppOutputAdapter
{
    public function sendTemplateWithMedia(
        Channel $channel,
        Contact $contact,
        string|WhatsAppTemplate $template,
        array $parameters,
        UploadedFile $media,
    ): Message;
}
