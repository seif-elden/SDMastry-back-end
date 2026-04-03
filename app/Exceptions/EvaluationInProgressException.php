<?php

namespace App\Exceptions;

use Exception;

class EvaluationInProgressException extends Exception
{
    public function __construct(string $message = 'Evaluation still in progress')
    {
        parent::__construct($message);
    }
}
