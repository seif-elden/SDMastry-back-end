<?php

namespace App\Exceptions;

use Exception;

class ChatProviderUnavailableException extends Exception
{
    public function __construct(string $message = "I'm having trouble thinking right now. Please try again in a moment.")
    {
        parent::__construct($message);
    }
}
