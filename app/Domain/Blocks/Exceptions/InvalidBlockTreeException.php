<?php

namespace App\Domain\Blocks\Exceptions;

/** A block tree failed per-type validation before any write (→ 422 with field paths). */
class InvalidBlockTreeException extends \InvalidArgumentException
{
    /** @param array<string,array<int,string>> $errors path → messages */
    public function __construct(public readonly array $errors)
    {
        $first = $errors === [] ? 'Invalid block tree.' : array_key_first($errors) . ': ' . $errors[array_key_first($errors)][0];
        parent::__construct($first);
    }
}
