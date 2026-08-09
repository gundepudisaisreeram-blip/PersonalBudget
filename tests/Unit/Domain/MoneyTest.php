<?php

namespace Tests\Unit\Domain;

use App\Domain\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_add_uses_exact_decimal_arithmetic(): void
    {
        $this->assertSame('19159.00', Money::add('10000.00', '9159.00'));
        $this->assertSame('0.30', Money::add('0.10', '0.20'));
    }

    public function test_sub_uses_exact_decimal_arithmetic(): void
    {
        $this->assertSame('9159.00', Money::sub('19159.00', '10000.00'));
    }

    public function test_compare(): void
    {
        $this->assertSame(0, Money::compare('100.00', '100.00'));
        $this->assertSame(1, Money::compare('100.01', '100.00'));
        $this->assertSame(-1, Money::compare('99.99', '100.00'));
    }

    public function test_is_positive(): void
    {
        $this->assertTrue(Money::isPositive('0.01'));
        $this->assertFalse(Money::isPositive('0.00'));
        $this->assertFalse(Money::isPositive('-0.01'));
    }

    public function test_is_zero(): void
    {
        $this->assertTrue(Money::isZero('0.00'));
        $this->assertFalse(Money::isZero('0.01'));
    }

    public function test_is_greater_than(): void
    {
        $this->assertTrue(Money::isGreaterThan('19200.00', '19159.00'));
        $this->assertFalse(Money::isGreaterThan('19159.00', '19159.00'));
    }

    public function test_is_greater_than_or_equal(): void
    {
        $this->assertTrue(Money::isGreaterThanOrEqual('19159.00', '19159.00'));
        $this->assertTrue(Money::isGreaterThanOrEqual('19200.00', '19159.00'));
        $this->assertFalse(Money::isGreaterThanOrEqual('19158.99', '19159.00'));
    }
}
