<?php

namespace App\Exceptions\Flows;

use RuntimeException;

class FlowEventWebhookDeliveryException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $responseStatus = null)
    {
        parent::__construct($message);
    }
}
