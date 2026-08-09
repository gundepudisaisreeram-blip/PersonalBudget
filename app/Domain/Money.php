<?php

namespace App\Domain;

/**
 * Exact decimal arithmetic for authoritative financial calculations.
 *
 * PHP floats are forbidden for financial calculations (BR-003). All amounts
 * are handled as numeric strings using bcmath, matching the DECIMAL(15,2)
 * database columns.
 */
final class Money
{
    public const SCALE = 2;

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, self::SCALE);
    }

    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    public static function compare(string $a, string $b): int
    {
        return bccomp($a, $b, self::SCALE);
    }

    public static function isPositive(string $a): bool
    {
        return self::compare($a, '0') > 0;
    }

    public static function isZero(string $a): bool
    {
        return self::compare($a, '0') === 0;
    }

    public static function isGreaterThan(string $a, string $b): bool
    {
        return self::compare($a, $b) > 0;
    }

    public static function isGreaterThanOrEqual(string $a, string $b): bool
    {
        return self::compare($a, $b) >= 0;
    }
}
