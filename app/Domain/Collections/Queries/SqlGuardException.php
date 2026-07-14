<?php

namespace App\Domain\Collections\Queries;

/** A guarded-SQL rule was violated (parse-time) or enforcement fired (runtime). */
class SqlGuardException extends \RuntimeException
{
}
