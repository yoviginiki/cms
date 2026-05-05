<?php

namespace App\Services\Theme\Exceptions;

class CircularTokenReferenceException extends \RuntimeException
{
    public function __construct(public array $cycle)
    {
        parent::__construct('Circular token reference detected: ' . implode(' → ', $cycle));
    }
}
