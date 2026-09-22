<?php

namespace App\Flows;

use RuntimeException;

final class TraceableFlowException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $traceData
     */
    public function __construct(string $message, public readonly array $traceData)
    {
        parent::__construct($message);
    }
}
