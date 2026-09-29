<?php

namespace App\Crm;

use RuntimeException;

class CrmProviderException extends RuntimeException
{
    public function __construct(
        public readonly CrmErrorCategory $category,
        string $message,
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
