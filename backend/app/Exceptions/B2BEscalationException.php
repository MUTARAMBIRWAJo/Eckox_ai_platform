<?php

namespace App\Exceptions;

use Exception;

class B2BEscalationException extends Exception
{
    public function __construct(string $reason, int $code = 0, \Throwable $previous = null)
    {
        parent::__construct("Escalation triggered: " . $reason, $code, $previous);
    }
}
