<?php

namespace App\Enums;

enum WhatsAppConnectionMethod: string
{
    case Manual = 'manual';
    case EmbeddedSignup = 'embedded_signup';
}
