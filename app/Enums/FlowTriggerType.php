<?php

namespace App\Enums;

enum FlowTriggerType: string
{
    case Message = 'message';
    case Webhook = 'webhook';
    case Manual = 'manual';
}
