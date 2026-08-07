<?php

namespace App\Exceptions;

use RuntimeException;

class CapacityExceededException extends RuntimeException
{
    public function __construct(public readonly string $limitKey, string $message)
    {
        parent::__construct($message);
    }
}
