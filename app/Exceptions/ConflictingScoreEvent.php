<?php

namespace App\Exceptions;

use RuntimeException;

final class ConflictingScoreEvent extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This event_id already exists with different event data.');
    }
}
