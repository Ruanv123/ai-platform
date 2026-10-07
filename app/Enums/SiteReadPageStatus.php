<?php

namespace App\Enums;

enum SiteReadPageStatus: string
{
    case Pending = 'pending';
    case Read = 'read';
    case Failed = 'failed';
}
