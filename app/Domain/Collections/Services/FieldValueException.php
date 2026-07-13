<?php

namespace App\Domain\Collections\Services;

/**
 * A single field value failed type coercion/validation inside
 * RecordDataProcessor. Internal — always caught and folded into one
 * ValidationException per record.
 */
class FieldValueException extends \RuntimeException
{
}
