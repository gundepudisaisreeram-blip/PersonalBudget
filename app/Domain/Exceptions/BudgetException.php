<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a budget operation would violate a domain rule, such as
 * overlapping another budget for the same category and period.
 */
class BudgetException extends RuntimeException {}
