<?php

namespace App\Domain\Blocks\Exceptions;

/** The caller's expected content revision no longer matches the stored one (→ 409). */
class StaleContentRevisionException extends \RuntimeException
{
    public function __construct(
        public readonly string $expected,
        public readonly ?string $current = null,
    ) {
        parent::__construct('These blocks were modified by someone else since you loaded them. Reload to get the latest version.');
    }
}
