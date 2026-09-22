<?php

namespace App\Domain\Publishing\Exceptions;

/**
 * A build failure that another attempt cannot fix (hard output errors,
 * superseded/reaped deployment, missing rollback target). The job marks the
 * deployment failed and does NOT retry (F16/F27).
 */
class NonRetryableBuildException extends \RuntimeException
{
}
