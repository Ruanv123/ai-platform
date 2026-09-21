<?php

namespace App\Enums;

enum FlowCollisionPolicy: string
{
    case Ignore = 'ignore';
    case Queue = 'queue';
    case Replace = 'replace';
}
