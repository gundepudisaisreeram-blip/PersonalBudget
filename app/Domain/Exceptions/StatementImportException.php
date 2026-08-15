<?php

namespace App\Domain\Exceptions;

use RuntimeException;

/**
 * Thrown when a statement import operation would violate a domain rule,
 * such as re-uploading the exact same file for an account (PHASE_7
 * Decision Package section 16, "File Idempotency").
 */
class StatementImportException extends RuntimeException {}
