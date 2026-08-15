<?php

namespace App\Domain\Statements\Exceptions;

use Exception;

/**
 * Thrown by a file reader or bank profile when the file is structurally
 * invalid, corrupt, insecure, or fails to parse under a selected profile.
 * Always caught by the caller and converted into a graceful rejection --
 * never allowed to surface as an unhandled 500 (PHASE_7_DECISION_PACKAGE.md
 * section 10).
 */
class StatementParseException extends Exception {}
