<?php

namespace App\Enums;

enum WhatsAppConnectionMethod: string
{
    case TechProvider = 'tech_provider';
    case OwnApp = 'own_app';
}
