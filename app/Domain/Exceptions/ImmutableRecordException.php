<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when code attempts to update or delete a posted financial record.
 * Corrections must be made via Refund, Reversal, or Adjustment instead.
 */
class ImmutableRecordException extends RuntimeException {}
