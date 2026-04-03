<?php

namespace App\Exceptions;

use Exception;

class AttemptAccessDeniedException extends Exception
{
    public function __construct(string $message = 'You are not allowed to access this attempt.')
    {
        parent::__construct($message);
    }
}
