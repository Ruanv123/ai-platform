<?php

namespace App\Enums;

enum FlowStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Paused = 'paused';
}
