<?php

namespace App\Exceptions;

use RuntimeException;

class StaleBedUpdateException extends RuntimeException
{
    public function __construct(
        public readonly int $bedId,
        string $message = 'This bed was updated by another user. Reload the bed board and try again.'
    ) {
        parent::__construct($message);
    }
}
