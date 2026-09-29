<?php

namespace App\Crm;

enum CrmErrorCategory: string
{
    case Temporary = 'temporary';
    case RateLimit = 'rate_limit';
    case AccessDenied = 'access_denied';
    case MissingReference = 'missing_reference';
    case Definitive = 'definitive';
}
