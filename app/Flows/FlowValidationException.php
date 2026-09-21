<?php

namespace App\Flows;

use RuntimeException;
use Throwable;

final class FlowValidationException extends RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors, ?Throwable $previous = null)
    {
        parent::__construct(implode(' ', $errors), previous: $previous);
    }
}
