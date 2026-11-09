<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown when an enrollment action breaks a rule. Carries one or more readable messages. */
class EnrollmentRuleException extends RuntimeException
{
    /** @param string[] $messages */
    public function __construct(public array $messages)
    {
        parent::__construct(implode(' ', $messages));
    }
}
