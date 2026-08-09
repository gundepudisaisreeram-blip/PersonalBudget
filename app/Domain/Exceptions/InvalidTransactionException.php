<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a transaction or its ledger entries would violate the
 * approved V1 ledger movement patterns.
 */
class InvalidTransactionException extends RuntimeException {}
