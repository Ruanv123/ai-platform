<?php

namespace App\Enums;

enum FlowCategory: string
{
    case Conversation = 'conversation';
    case Reaction = 'reaction';
}
