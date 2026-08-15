<?php

namespace App\Domain\Statements\Exceptions;

use Exception;

/**
 * Thrown when zero registered bank profiles match a file's content, or the
 * file's declared/sniffed format is outside V1 scope (legacy XLS, OFX,
 * QIF) -- PHASE_7_DECISION_PACKAGE.md section 5, "Zero Matches".
 */
class UnsupportedStatementFormatException extends Exception {}
