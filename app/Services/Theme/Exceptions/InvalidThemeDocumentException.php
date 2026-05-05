<?php

namespace App\Services\Theme\Exceptions;

class InvalidThemeDocumentException extends \RuntimeException
{
    public function __construct(public array $errors)
    {
        parent::__construct('Invalid theme document: ' . json_encode($errors, JSON_PRETTY_PRINT));
    }
}
