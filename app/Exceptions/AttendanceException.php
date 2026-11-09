<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when an attendance sheet breaks a rule. Carries one or more readable messages. */
class AttendanceException extends RuntimeException
{
    /** @param string[] $messages */
    public function __construct(public array $messages)
    {
        parent::__construct(implode(' ', $messages));
    }
}
