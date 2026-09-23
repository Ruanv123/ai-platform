<?php

namespace App\Exceptions;

use RuntimeException;

class SchedulingException extends RuntimeException
{
    public static function unavailable(string $message = 'A Agenda está indisponível. Reconecte a conta Google.'): self
    {
        return new self($message);
    }
}
