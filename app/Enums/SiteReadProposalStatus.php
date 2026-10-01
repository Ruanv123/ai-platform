<?php

namespace App\Enums;

enum SiteReadProposalStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Discarded = 'discarded';
    case Replaced = 'replaced';
}
