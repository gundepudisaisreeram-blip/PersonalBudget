<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a request attempts to reference another user's financial data.
 */
class OwnershipViolationException extends RuntimeException {}
