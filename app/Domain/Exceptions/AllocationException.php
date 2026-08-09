<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when an obligation allocation would violate eligibility, amount,
 * or aggregate-limit rules.
 */
class AllocationException extends RuntimeException {}
